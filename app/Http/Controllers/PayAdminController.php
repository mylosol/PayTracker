<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\DriverLoad;
use PayTracker\Models\PayRate;
use PayTracker\Models\PayRateVersion;
use PayTracker\Models\PayVariable;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\Pay\LoadInputs;
use PayTracker\Services\Pay\PayRecomputer;
use PayTracker\Services\Pay\PayWeek;
use PayTracker\Services\Pay\UnsavedLoads;
use PayTracker\Services\Pay\VariableBlobBuilder;
use PayTracker\Services\PayCalculator;

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
        private readonly DriverLoad $loads,
        private readonly PayVariable $payVariables,
        private readonly UnsavedLoads $unsavedLoads,
        private readonly VariableBlobBuilder $blobBuilder,
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
     * POST /pay-admin/draft/bump — multiply every draft tier by
     * (1 + percent/100). Auto-starts a draft from current if none
     * exists. This is a calculator-button convenience for the common
     * "payroll gave a 7% raise" case; the resulting rates are
     * absolute values stored in the draft, and Peter still reviews
     * + promotes normally with an effective_date.
     */
    public function bumpDraft(Request $request): Response
    {
        return $this->guard($request, function (string $tripType) use ($request): string {
            $percentRaw = trim((string) $request->input('percent', ''));
            // Accept optional leading + / -, integer or decimal.
            if (! preg_match('/^[+-]?\d+(\.\d{1,4})?$/', $percentRaw)) {
                throw new \InvalidArgumentException(
                    'Percent must be a number, optionally with a leading +/- and up to 4 decimals.'
                );
            }
            $percent = (float) $percentRaw;
            $count   = $this->rates->bumpDraftByPercent($tripType, $percent);
            $verb    = $percent >= 0 ? 'raised' : 'cut';
            return sprintf(
                '%s %s draft by %s%% (%d tier%s). Review the numbers, then click Promote with an effective date.',
                ucfirst($verb),
                $tripType,
                rtrim(rtrim(number_format(abs($percent), 4, '.', ''), '0'), '.'),
                $count,
                $count === 1 ? '' : 's',
            );
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
     * GET  /pay-admin/preview[?trip_type=round_trip|long_haul][&date=YYYY-MM-DD]
     * POST /pay-admin/preview  (same params + `unsaved_loads` JSON)
     *
     * Dry-run the draft rates against the loads the VIEWER sees on their
     * own dashboard for the pay week containing `date` (default: today).
     *
     * Default scope is EVERY trip type. That matters: the question an
     * admin is really asking is "what does this do to my week", and the
     * dashboard's This Week card sums every load the driver has — one-ways
     * included. A per-type-only page silently dropped the one-way loads and
     * could never be reconciled against the dashboard. `?trip_type=…`
     * still narrows to a single type for the per-card button on
     * /pay-admin.
     *
     * Drafts are seeded per trip type on ONE calculator (PayCalculator
     * resolves the tier set from each row's own load_type), so a round-trip
     * draft and a long-haul draft are previewed together. A type with no
     * draft is compared at current rates and labelled — visible, not
     * hidden. Trainer rows (load_type 4) carry no tiers and are shown
     * unchanged. Rows whose legacy load_type the calculator can't price
     * keep their stored pay and are flagged, so the totals still reconcile
     * with the dashboard card.
     *
     * Scope is the signed-in account's own loads — never the fleet's. See
     * DriverLoad::forPreviewForDriver() for why that predicate is
     * load-bearing: the first revision repriced "every load in the window"
     * and rendered other drivers' handles, routes, dates and pay on an
     * admin+ page. Fleet-wide figures belong on the super_admin QA surface
     * (/loads), not here.
     *
     * Unconfirmed (in-browser) loads. A driver with "Store Load Info" OFF
     * keeps loads in localStorage, and the dashboard hydrates them — so a
     * preview that reads only `driver_loads` disagrees with the very
     * dashboard it claims to project. The page's own JS posts that
     * scratchpad array back here on POST; UnsavedLoads validates it and we
     * reprice the entries with the same calculators, never trusting the
     * browser's money figures. Nothing is written in either case — the DB
     * is untouched.
     */
    public function preview(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }

        // '' = every trip type (the default view). An explicit value is the
        // per-type deep-link from the /pay-admin rate cards.
        $tripType = (string) $request->input('trip_type', '');
        if ($tripType !== '' && ! in_array($tripType, PayRate::TRIP_TYPES, true)) {
            $this->session->put('_flash', 'Unknown trip_type in preview request.');
            return $this->redirect($request->basePath() . '/pay-admin');
        }
        $focused = $tripType !== '';

        // Anchor day for the pay week we preview. Same guard as the
        // dashboard's ?date= so both surfaces agree on which week "now"
        // means when deciding what counts as a dashboard load.
        $dateRaw = (string) $request->input('date', '');
        $anchor  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) === 1 ? $dateRaw : date('Y-m-d');

        // Which drafts exist. A draft is what makes this a preview rather
        // than an echo of current pay.
        $hasDraft = [];
        foreach (PayRate::TRIP_TYPES as $type) {
            $hasDraft[$type] = $this->rates->hasDraft($type);
        }
        if (! in_array(true, $hasDraft, true)) {
            $this->session->put('_flash', 'No drafts yet. Start a draft (or Bump %) before running Preview.');
            return $this->redirect($request->basePath() . '/pay-admin');
        }
        if ($focused && ! $hasDraft[$tripType]) {
            $this->session->put('_flash', sprintf(
                'No draft for %s yet. Start Draft (or Bump %%) for it, or preview every trip type.',
                $tripType,
            ));
            return $this->redirect($request->basePath() . '/pay-admin');
        }

        // trip_type ↔ load_type. Explicit map so a future trip_type shows up
        // here rather than silently misrouting.
        $tripLoadTypes = ['round_trip' => 1, 'long_haul' => 0];

        // Projection calculator, seeded with every draft in scope.
        $draftCalc = new PayCalculator($this->rateVersions, $this->payVariables);
        foreach (array_keys($tripLoadTypes) as $type) {
            if (! $hasDraft[$type] || ($focused && $type !== $tripType)) {
                continue;
            }
            $draftCalc->setRateTiersForTest($type, array_map(
                static fn (array $t): array => ['miles' => (int) $t['miles'], 'rate' => (float) $t['rate']],
                $this->rates->tiers($type, 'draft'),
            ));
        }

        // Current-rate calculator (unseeded → the live version-anchored
        // tiers). Unconfirmed rows get their "current" figure from here
        // rather than from the browser.
        $currentCalc = new PayCalculator($this->rateVersions, $this->payVariables);

        // Load types the calculator can price: 0 one-way, 1 round-trip,
        // 4 trainer. Anything else in the window keeps its stored pay.
        $repricable = [0, 1, 4];
        $scopeTypes = $focused ? [$tripLoadTypes[$tripType]] : [];

        // Scope: the viewer's own loads, in the pay week they'd be looking
        // at on /dashboard. The driver_id comes from the authenticated
        // account, never from the request, so there is no parameter an
        // admin could point at another driver's rows.
        $week     = PayWeek::containing($account, $anchor);
        $viewerId = (int) $account['id'];
        $today    = date('Y-m-d');
        $rows     = $this->loads->forPreviewForDriver(
            $viewerId,
            $scopeTypes,
            $week['since'],
            $week['until'],
            500,
        );

        // Sub-total buckets, keyed by load_type string ('?' = a row the
        // calculator can't price).
        $bucketMeta = [
            '1' => ['label' => 'Round-trip', 'trip_type' => 'round_trip'],
            '0' => ['label' => 'One-way',    'trip_type' => 'long_haul'],
            '4' => ['label' => 'Trainer',    'trip_type' => null],
            '?' => ['label' => 'Not repriced', 'trip_type' => null],
        ];

        $comparisons = [];
        $buckets     = [];

        $bucketFor = function (string $key) use (&$buckets, $bucketMeta, $hasDraft, $repricable): void {
            if (isset($buckets[$key])) {
                return;
            }
            $meta       = $bucketMeta[$key] ?? $bucketMeta['?'];
            $tierType   = $meta['trip_type'];
            $buckets[$key] = [
                'key'         => $key,
                'label'       => $meta['label'],
                'trip_type'   => $tierType,
                'count'       => 0,
                'saved_count' => 0,
                'unsaved_count' => 0,
                'old'         => 0.0,
                'new'         => 0.0,
                'repriced'    => in_array((int) $key, $repricable, true),
                // null = tiers don't apply to this bucket at all.
                'has_draft'   => $tierType !== null ? $hasDraft[$tierType] : null,
            ];
        };

        foreach ($rows as $row) {
            $stored   = (float) ($row['np'] ?? 0);
            $rowType  = is_numeric($row['load_type'] ?? null) ? (int) $row['load_type'] : -1;
            $pricable = in_array($rowType, $repricable, true);
            $key      = $pricable ? (string) $rowType : '?';

            $projection = null;
            $newPay     = $stored;

            if ($pricable) {
                try {
                    $input = new LoadInputs(
                        load_type:          $rowType,
                        load_miles:         (int) ($row['empty_miles'] ?? 0), // legacy misnomer — loaded miles
                        empty_miles:        (int) ($row['end_empty_miles'] ?? 0),
                        begin_empty_miles:  (int) ($row['begin_empty_miles'] ?? 0),
                        is_split:           (int) ($row['is_split'] ?? 0),
                        is_weekend:         (int) ($row['is_weekend'] ?? 0),
                        is_backhaul:        (int) ($row['is_backhaul'] ?? 0),
                        extra_pay:          (float) ($row['extra_pay'] ?? 0),
                        dem_minutes:        (int) ($row['dem_minutes'] ?? 0),
                        break_minutes:      (int) ($row['break_minutes'] ?? 0),
                        variables_blob:     (string) ($row['variables'] ?? '168-day--0'),
                        out_of_route_ind:   (int) ($row['out_of_route_ind'] ?? 0),
                        out_of_route_miles: (int) ($row['out_of_route_miles'] ?? 0),
                        load_date:          isset($row['date']) ? substr((string) $row['date'], 0, 10) : '',
                    );
                    $projection = $draftCalc->computeFor($input);
                    $newPay     = (float) $projection['np'];
                } catch (\Throwable $e) {
                    // A single bad row shouldn't blank the preview — fall
                    // back to its stored pay and let the aggregate surface.
                    $projection = null;
                    $newPay     = $stored;
                }
            }

            $bucketFor($key);
            $comparisons[] = [
                'source'        => 'saved',
                'bucket'        => $key,
                'repriced'      => $pricable,
                'frtl'          => (int) ($row['frtl'] ?? 0),
                'local_id'      => null,
                'date'          => isset($row['date']) ? substr((string) $row['date'], 0, 10) : '',
                'pickup'        => (string) ($row['pickup_city'] ?? ''),
                'delivery'      => (string) ($row['delivery_city'] ?? ''),
                'notes'         => (string) ($row['notes'] ?? ''),
                'old_np'        => $stored,
                'new_np'        => $newPay,
                'delta'         => $newPay - $stored,
                'new_breakdown' => $projection,
            ];
            $buckets[$key]['count']++;
            $buckets[$key]['saved_count']++;
            $buckets[$key]['old'] += $stored;
            $buckets[$key]['new'] += $newPay;
        }

        // --- Unconfirmed (in-browser) loads ---------------------------------
        // Only on POST: the entries live in the browser, so the page has to
        // hand them over before the server can reprice them.
        $unsaved = ['entries' => [], 'dropped' => 0, 'truncated' => false, 'error' => null];
        if ($request->isMethod('POST')) {
            $this->session->start();
            if (! $this->csrf->verify($request->input('_csrf'))) {
                return $this->failBack('Your session expired. Please try again.', $request);
            }
            $unsaved = $this->unsavedLoads->parse((string) $request->input('unsaved_loads', ''));
        }

        // The dashboard hydrates scratchpad rows for TODAY only — past and
        // future dates render DB-backed loads by spec. Mirror that exactly so
        // the preview reconciles with the card it is projecting, rather than
        // inventing rows the driver can't see.
        $weekHasToday = $today >= $week['start'] && $today <= $week['end'];
        $unsavedShown = 0;

        if ($unsaved['entries'] !== [] && ! $weekHasToday) {
            $unsaved['error'] = 'Unconfirmed loads only appear on today\'s dashboard, so they are not part of a past or future week. Preview the week containing today to include them.';
        } elseif ($unsaved['entries'] !== []) {
            $unsavedTypes = $focused ? [$tripLoadTypes[$tripType]] : $repricable;
            $blob         = $this->blobBuilder->build($account);

            foreach ($unsaved['entries'] as $entry) {
                $entryType = (int) $entry['load_type'];
                if (! in_array($entryType, $unsavedTypes, true)) {
                    continue;
                }
                if ((string) $entry['date'] !== $today) {
                    continue;
                }

                try {
                    $inputs     = $this->unsavedLoads->toLoadInputs($entry, $blob);
                    $projection = $draftCalc->computeFor($inputs);
                    $oldPay     = (float) $currentCalc->computeFor($inputs)['np'];
                    $newPay     = (float) $projection['np'];
                } catch (\Throwable $e) {
                    $unsaved['dropped']++;
                    continue;
                }

                $key = (string) $entryType;
                $bucketFor($key);
                $comparisons[] = [
                    'source'        => 'unsaved',
                    'bucket'        => $key,
                    'repriced'      => true,
                    'frtl'          => null,
                    'local_id'      => (string) $entry['local_id'],
                    'date'          => (string) $entry['date'],
                    'pickup'        => (string) $entry['pickup_city'],
                    'delivery'      => (string) $entry['delivery_city'],
                    'notes'         => (string) $entry['notes'],
                    'old_np'        => $oldPay,
                    'new_np'        => $newPay,
                    'delta'         => $newPay - $oldPay,
                    'new_breakdown' => $projection,
                ];
                $buckets[$key]['count']++;
                $buckets[$key]['unsaved_count']++;
                $buckets[$key]['old'] += $oldPay;
                $buckets[$key]['new'] += $newPay;
                $unsavedShown++;
            }
        }

        // Unconfirmed rows first (they're the ones just entered), then
        // newest-first, which is how the dashboard tables read.
        usort($comparisons, static function (array $a, array $b): int {
            $byDate = strcmp((string) $b['date'], (string) $a['date']);
            if ($byDate !== 0) {
                return $byDate;
            }
            return ($b['source'] === 'unsaved' ? 1 : 0) <=> ($a['source'] === 'unsaved' ? 1 : 0);
        });

        // Bucket order: round-trip, one-way, trainer, then the unpriced
        // leftovers. Keeps the sub-total strip stable between renders.
        $bucketOrder = ['1' => 0, '0' => 1, '4' => 2, '?' => 3];
        uksort($buckets, static fn (string $a, string $b): int => ($bucketOrder[$a] ?? 9) <=> ($bucketOrder[$b] ?? 9));

        $totalOld = 0.0;
        $totalNew = 0.0;
        $savedOld = 0.0;
        $savedNew = 0.0;
        $unsavedOld = 0.0;
        $unsavedNew = 0.0;
        foreach ($buckets as $bucket) {
            $totalOld += $bucket['old'];
            $totalNew += $bucket['new'];
        }
        foreach ($comparisons as $comparison) {
            if ($comparison['source'] === 'saved') {
                $savedOld += $comparison['old_np'];
                $savedNew += $comparison['new_np'];
            } else {
                $unsavedOld += $comparison['old_np'];
                $unsavedNew += $comparison['new_np'];
            }
        }

        $deltaTotal = $totalNew - $totalOld;
        $deltaPct   = $totalOld > 0 ? ($deltaTotal / $totalOld) * 100.0 : 0.0;

        if ($unsaved['truncated']) {
            $note = sprintf(
                'Only the newest %d unconfirmed loads were previewed (the rest are still in the browser).',
                UnsavedLoads::MAX_ENTRIES,
            );
            $unsaved['error'] = $unsaved['error'] === null ? $note : $unsaved['error'] . ' ' . $note;
        }
        if ($unsaved['dropped'] > 0) {
            $note = sprintf('%d unconfirmed load(s) could not be read and were left out.', $unsaved['dropped']);
            $unsaved['error'] = $unsaved['error'] === null ? $note : $unsaved['error'] . ' ' . $note;
        }

        return $this->view('pay-admin/preview', [
            'base'              => $request->basePath(),
            'actor'             => $account,
            'scope'             => $focused ? 'focused' : 'all',
            'trip_type'         => $tripType,
            'trip_label'        => $focused ? $this->tripLabel($tripType) : 'All trip types',
            'anchor'            => $anchor,
            'today'             => $today,
            'week_start'        => $week['start'],
            'week_end'          => $week['end'],
            'week_start_day'    => $week['start_day'],
            'week_has_today'    => $weekHasToday,
            'has_draft'         => $hasDraft,
            'buckets'           => array_values($buckets),
            'row_count'         => count($comparisons),
            'saved_count'       => count($rows),
            'unsaved_count'     => $unsavedShown,
            'unsaved_requested' => $request->isMethod('POST'),
            'unsaved_live'      => count($unsaved['entries']),
            'unsaved_error'     => $unsaved['error'],
            'total_old'         => $totalOld,
            'total_new'         => $totalNew,
            'delta_total'       => $deltaTotal,
            'delta_pct'         => $deltaPct,
            'total_old_saved'   => $savedOld,
            'total_new_saved'   => $savedNew,
            'total_old_unsaved' => $unsavedOld,
            'total_new_unsaved' => $unsavedNew,
            'comparisons'       => $comparisons,
            'scope_load_types'  => $focused ? [$tripLoadTypes[$tripType]] : $repricable,
            'current_tiers'     => [
                'round_trip' => $this->rates->tiers('round_trip', 'current'),
                'long_haul'  => $this->rates->tiers('long_haul', 'current'),
            ],
            'draft_tiers'       => [
                'round_trip' => $hasDraft['round_trip'] ? $this->rates->tiers('round_trip', 'draft') : [],
                'long_haul'  => $hasDraft['long_haul'] ? $this->rates->tiers('long_haul', 'draft') : [],
            ],
            'csrf_token'        => $this->csrf->token(),
        ]);
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
