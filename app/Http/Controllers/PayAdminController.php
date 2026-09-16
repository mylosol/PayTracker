<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\PayRate;
use PayTracker\Models\PayRateVersion;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\Pay\PayRecomputer;

/**
 * PayAdminController — modern replacement for the legacy pay-admin
 * pages, simplified after the Pensacola/Panama rate duplication was
 * collapsed. One editor card per trip_type (round_trip, long_haul);
 * no terminal picker.
 *
 * Read flow (GET /pay-admin)
 *   - Top-level summary across trip types.
 *   - Per-trip_type editor: current rates + draft (if any).
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
 * Auth posture: admin+ on every action (view AND edit). The pay-rate
 * tables drive how every load is paid; mis-editing them silently
 * shifts every driver's paycheck. The base User role gets a 403 on
 * GET, not a redirect, so a curious driver isn't pushed back through
 * the login flow.
 */
final class PayAdminController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PayRate $rates,
        private readonly PayRateVersion $rateVersions,
        private readonly PayRecomputer $recomputer,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();

        $buckets = [];
        foreach (PayRate::TRIP_TYPES as $tripType) {
            $buckets[] = [
                'trip_type' => $tripType,
                'label'     => $this->tripLabel($tripType),
                'current'   => $this->rates->tiers($tripType, 'current'),
                'draft'     => $this->rates->tiers($tripType, 'draft'),
                'has_draft' => $this->rates->hasDraft($tripType),
            ];
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
     * the given trip_type, copied from current.
     */
    public function startDraft(Request $request): Response
    {
        return $this->guard($request, function (string $tripType): string {
            $this->rates->startOrResetDraft($tripType);
            return sprintf('Draft started for %s.', $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/upsert — insert or update a (miles, rate) tier
     * in the draft.
     */
    public function upsertDraftTier(Request $request): Response
    {
        return $this->guard($request, function (string $tripType) use ($request): string {
            $milesRaw = (string) $request->input('miles', '');
            $rateRaw  = trim((string) $request->input('rate', ''));
            if (! ctype_digit($milesRaw) || (int) $milesRaw <= 0) {
                throw new \InvalidArgumentException('Miles must be a positive integer.');
            }
            if (! preg_match('/^\d+(\.\d{1,4})?$/', $rateRaw)) {
                throw new \InvalidArgumentException('Rate must be dollars or dollars.cents (up to 4 decimals).');
            }
            $this->rates->upsertDraftTier($tripType, (int) $milesRaw, $rateRaw);
            return sprintf('Saved tier %d → %s in %s draft.', (int) $milesRaw, $rateRaw, $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/delete — remove a (miles) tier from the draft.
     */
    public function deleteDraftTier(Request $request): Response
    {
        return $this->guard($request, function (string $tripType) use ($request): string {
            $milesRaw = (string) $request->input('miles', '');
            if (! ctype_digit($milesRaw) || (int) $milesRaw <= 0) {
                throw new \InvalidArgumentException('Miles must be a positive integer.');
            }
            $this->rates->deleteDraftTier($tripType, (int) $milesRaw);
            return sprintf('Deleted tier %d from %s draft.', (int) $milesRaw, $tripType);
        });
    }

    /**
     * POST /pay-admin/draft/promote — atomic draft → current AND
     * snapshot the new tier set into pay_rate_versions so historical
     * loads keep billing at the rate that was active when the work
     * was done.
     *
     * Effective date input is optional; an empty value defaults to
     * today. Backdating is allowed (e.g. raise went into effect Aug
     * 1, admin clicked Promote on Aug 5 with effective_date=Aug 1).
     */
    public function promoteDraft(Request $request): Response
    {
        return $this->guard($request, function (string $tripType) use ($request): string {
            $effectiveRaw = trim((string) $request->input('effective_date', ''));
            if ($effectiveRaw === '') {
                $effectiveDate = date('Y-m-d');
            } else {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveRaw) !== 1) {
                    throw new \InvalidArgumentException('Effective date must be YYYY-MM-DD.');
                }
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $effectiveRaw);
                if ($parsed === false || $parsed->format('Y-m-d') !== $effectiveRaw) {
                    throw new \InvalidArgumentException('Effective date is not a valid calendar date.');
                }
                $effectiveDate = $effectiveRaw;
            }

            // Read the draft tiers BEFORE promote — promoteDraftToCurrent
            // clears them as part of the atomic transaction, so a later
            // read would see an empty draft.
            $draftTiers = $this->rates->tiers($tripType, 'draft');
            if ($draftTiers === []) {
                throw new \InvalidArgumentException(
                    "No draft exists for {$tripType} — nothing to promote.",
                );
            }

            $this->rates->promoteDraftToCurrent($tripType);
            $this->rateVersions->createVersionFromTiers(
                $tripType,
                $effectiveDate,
                array_map(
                    static fn (array $t): array => ['miles' => (int) $t['miles'], 'rate' => (string) $t['rate']],
                    $draftTiers,
                ),
            );
            return sprintf(
                'Promoted %s draft → current, effective %s. Historical loads pre-%s keep their previous rate.',
                $tripType,
                $effectiveDate,
                $effectiveDate,
            );
        });
    }

    /**
     * POST /pay-admin/recompute — walk driver_loads and refill np/op via
     * PayCalculator using current rates + variables.
     *
     * Scope: by default, only loads from the last 30 days (sinceDate filter)
     * to bound the runtime on the preview channel. The filter is a request
     * param so the admin can broaden if needed.
     */
    public function recompute(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
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
        return $this->guard($request, function (string $tripType): string {
            $this->rates->resetCurrentToDefault($tripType);
            return sprintf('Reset %s rates to defaults.', $tripType);
        });
    }

    /**
     * Common shell for all POST handlers: auth → CSRF → trip_type validation
     * → invoke the per-action body → flash + redirect.
     *
     * @param callable(string): string $body Action body returning a flash message.
     */
    private function guard(Request $request, callable $body): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        // RBAC: pay-rate writes must be admin+. A base User who somehow
        // POSTs to /pay-admin/draft/* (CSRF-forged or hand-crafted) gets
        // a 403 instead of a silent success.
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack('Your session expired. Please try again.', $request);
        }

        $tripType = (string) $request->input('trip_type', '');
        if (! in_array($tripType, PayRate::TRIP_TYPES, true)) {
            return $this->failBack('Unknown trip type.', $request);
        }

        try {
            $message = $body($tripType);
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

    private function tripLabel(string $tripType): string
    {
        return match ($tripType) {
            'round_trip' => 'Round-trip',
            'long_haul'  => 'Long-haul',
            default      => ucfirst($tripType),
        };
    }
}
