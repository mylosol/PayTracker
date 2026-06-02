<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\PayRate;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\Pay\PayRecomputer;

/**
 * PayAdminController — modern replacement for BasePayAdminSubmit.php +
 * UpdatePanamaPay.php + UpdatePensacolaPay.php + adminPaySelect.php.
 *
 * Read flow (GET /pay-admin)
 *   - Top-level summary across all terminals + trip types.
 *   - Per-(terminal, trip_type) editor: current rates + draft (if any).
 *   - Action buttons:
 *       Start draft from current  → POST /pay-admin/draft/start
 *       Promote draft to current  → POST /pay-admin/draft/promote
 *       Reset current to default  → POST /pay-admin/reset
 *
 * Write flow
 *   - Inline per-tier edit (POST /pay-admin/draft/upsert with miles+rate).
 *   - Tier delete (POST /pay-admin/draft/delete with miles).
 *   - Add new tier reuses the upsert path.
 *
 * Differences from legacy
 *   - Single editor per (terminal, trip_type) replaces the four legacy
 *     pages (BasePayAdmin Pensacola RT, Pensacola LH, Panama, plus the
 *     cookie-driven adminPaySelect toggle).
 *   - Test/Temp shadow tables collapse to a single 'draft' stage.
 *   - CSRF check on every POST.
 *   - Auth gate matches all other modern admin surfaces.
 *
 * Auth posture: any authenticated account can view AND edit. Production
 * pay-admin in the legacy app is unauthenticated (relies on URL secrecy);
 * we tighten that on the modern surface but stop short of role gating,
 * which is a separate concern tracked for a future branch when the
 * account-roles model surfaces in the UI.
 */
final class PayAdminController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PayRate $rates,
        private readonly PayRecomputer $recomputer,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        // Each (terminal, trip_type) bucket renders its own editor card. We
        // pre-load current + draft tiers here so the view stays a dumb
        // template.
        $buckets = [];
        foreach (PayRate::TERMINALS as $terminal) {
            foreach (PayRate::TRIP_TYPES as $tripType) {
                // Panama only has round-trip in the legacy data; we still
                // show a long-haul bucket but the view will render it as
                // "no rates yet". Hide it for now since legacy never had it.
                if ($terminal === 'panama' && $tripType === 'long_haul') {
                    continue;
                }
                $buckets[] = [
                    'terminal'  => $terminal,
                    'trip_type' => $tripType,
                    'label'     => $this->bucketLabel($terminal, $tripType),
                    'current'   => $this->rates->tiers($terminal, $tripType, 'current'),
                    'draft'     => $this->rates->tiers($terminal, $tripType, 'draft'),
                    'has_draft' => $this->rates->hasDraft($terminal, $tripType),
                ];
            }
        }

        return $this->view('pay-admin/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'summary'   => $this->rates->summary(),
            'buckets'   => $buckets,
            'flash'     => $this->popFlash(),
        ]);
    }

    /**
     * POST /pay-admin/draft/start — start (or restart) a draft for
     * (terminal, trip_type), copied from current.
     */
    public function startDraft(Request $request): Response
    {
        return $this->guard($request, function (string $terminal, string $tripType): string {
            $this->rates->startOrResetDraft($terminal, $tripType);
            return sprintf('Draft started for %s (%s).', $terminal, $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/upsert — insert or update a (miles, rate) tier
     * in the draft.
     */
    public function upsertDraftTier(Request $request): Response
    {
        return $this->guard($request, function (string $terminal, string $tripType) use ($request): string {
            $milesRaw = (string) $request->input('miles', '');
            $rateRaw  = trim((string) $request->input('rate', ''));
            if (! ctype_digit($milesRaw) || (int) $milesRaw <= 0) {
                throw new \InvalidArgumentException('Miles must be a positive integer.');
            }
            if (! preg_match('/^\d+(\.\d{1,4})?$/', $rateRaw)) {
                throw new \InvalidArgumentException('Rate must be dollars or dollars.cents (up to 4 decimals).');
            }
            $this->rates->upsertDraftTier($terminal, $tripType, (int) $milesRaw, $rateRaw);
            return sprintf('Saved tier %d → %s in %s (%s) draft.', (int) $milesRaw, $rateRaw, $terminal, $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/delete — remove a (miles) tier from the draft.
     */
    public function deleteDraftTier(Request $request): Response
    {
        return $this->guard($request, function (string $terminal, string $tripType) use ($request): string {
            $milesRaw = (string) $request->input('miles', '');
            if (! ctype_digit($milesRaw) || (int) $milesRaw <= 0) {
                throw new \InvalidArgumentException('Miles must be a positive integer.');
            }
            $this->rates->deleteDraftTier($terminal, $tripType, (int) $milesRaw);
            return sprintf('Deleted tier %d from %s (%s) draft.', (int) $milesRaw, $terminal, $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/promote — atomic draft → current.
     */
    public function promoteDraft(Request $request): Response
    {
        return $this->guard($request, function (string $terminal, string $tripType): string {
            $this->rates->promoteDraftToCurrent($terminal, $tripType);
            return sprintf('Promoted draft to current for %s (%s).', $terminal, $tripType);
        });
    }

    /**
     * POST /pay-admin/recompute — walk driver_loads and refill np/op via
     * PayCalculator using current rates + variables.
     *
     * Scope: by default, only loads from the last 30 days (sinceDate filter)
     * to bound the runtime on the preview channel. The filter is a request
     * param so the admin can broaden if needed.
     *
     * This is intentionally NOT routed through guard() because it doesn't
     * take terminal/trip_type — the recompute is global. We still do the
     * auth + CSRF check inline.
     */
    public function recompute(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack('Your session expired. Please try again.', $request);
        }

        $sinceRaw = (string) $request->input('since', '');
        $driverRaw = (string) $request->input('driver_id', '');
        $driverFilter = ctype_digit($driverRaw) && (int) $driverRaw > 0 ? (int) $driverRaw : null;

        try {
            $stats = $this->recomputer->run($driverFilter, $sinceRaw);
        } catch (\Throwable $e) {
            return $this->failBack('Recompute failed: ' . $e->getMessage(), $request);
        }

        // Reconstruct the effective window for the flash banner — the
        // service applies the same fallback (30 days ago) but doesn't
        // surface what it landed on.
        $effectiveSince = $sinceRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sinceRaw)
            ? $sinceRaw
            : date('Y-m-d', strtotime('-30 days'));
        $scopeNote = $driverFilter !== null
            ? sprintf(' driver_id=%d, since %s', $driverFilter, $effectiveSince)
            : sprintf(' since %s', $effectiveSince);
        $this->session->put('_flash', sprintf(
            'Recompute complete (%s): considered=%d, updated=%d, unchanged=%d, skipped=%d.',
            trim($scopeNote),
            $stats['considered'],
            $stats['updated'],
            $stats['unchanged'],
            $stats['skipped'],
        ));
        return $this->redirect($request->basePath() . '/pay-admin');
    }

    /**
     * POST /pay-admin/reset — current ← default; also clears any draft.
     */
    public function resetCurrent(Request $request): Response
    {
        return $this->guard($request, function (string $terminal, string $tripType): string {
            $this->rates->resetCurrentToDefault($terminal, $tripType);
            return sprintf('Reset %s (%s) rates to defaults.', $terminal, $tripType);
        });
    }

    /**
     * Common shell for all POST handlers: auth → CSRF → bucket validation
     * → invoke the per-action body → flash + redirect. Centralising this
     * keeps the individual actions tight and consistent.
     *
     * @param callable(string, string): string $body Action body returning a flash message.
     */
    private function guard(Request $request, callable $body): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack('Your session expired. Please try again.', $request);
        }

        $terminal = (string) $request->input('terminal', '');
        $tripType = (string) $request->input('trip_type', '');
        if (! in_array($terminal, PayRate::TERMINALS, true)) {
            return $this->failBack('Unknown terminal.', $request);
        }
        if (! in_array($tripType, PayRate::TRIP_TYPES, true)) {
            return $this->failBack('Unknown trip type.', $request);
        }

        try {
            $message = $body($terminal, $tripType);
        } catch (\InvalidArgumentException $e) {
            return $this->failBack($e->getMessage(), $request);
        } catch (\Throwable $e) {
            return $this->failBack('Operation failed: ' . $e->getMessage(), $request);
        }

        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/pay-admin');
    }

    private function failBack(string $message, Request $request): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/pay-admin');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }

    private function bucketLabel(string $terminal, string $tripType): string
    {
        $terminalLabel = match ($terminal) {
            'pensacola' => 'Pensacola',
            'panama'    => 'Panama City',
            default     => ucfirst($terminal),
        };
        $tripLabel = match ($tripType) {
            'round_trip' => 'Round-trip',
            'long_haul'  => 'Long-haul',
            default      => ucfirst($tripType),
        };
        return "{$terminalLabel} — {$tripLabel}";
    }
}
