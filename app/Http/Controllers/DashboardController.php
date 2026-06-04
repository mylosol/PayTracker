<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\DriverLoad;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\Pay\PayRecomputer;
use PayTracker\Services\Pay\VariableBlobBuilder;

/**
 * DashboardController — modern replacement for the signed-in portion of
 * legacy index.php. Shows the current driver's loads for a date window
 * (default = today) with grand totals.
 *
 * Per-driver isolation: the view ALWAYS scopes to the signed-in account's
 * driver_id. There is no driver-picker — admins wanting to inspect another
 * driver's loads use /loads?driver_id=N, which is the read-only QA
 * surface.
 *
 * Date semantics:
 *   - Default window is "today" in APP_TIMEZONE (set in .env, e.g.
 *     America/Chicago) — that's what a driver expects "today's pay" to
 *     mean.
 *   - ?date=YYYY-MM-DD switches to that day (whole-day window).
 *   - The window is start-of-day inclusive, next-day-start exclusive.
 *
 * Pay totals read directly from the stored `np`/`op` columns. They are
 * accurate to the LAST run of /pay-admin/recompute. If the admin never
 * ran recompute, stored totals can be zero — the view surfaces a "may
 * be stale; ask admin to recompute" note when np_total is zero with
 * a non-zero load count.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly DriverLoad $loads,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PayRecomputer $recomputer,
        private readonly VariableBlobBuilder $blobBuilder,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }

        $driverId = (int) $account['id'];

        $dateRaw = (string) $request->input('date', '');
        $dateRaw = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) ? $dateRaw : date('Y-m-d');

        $since = $dateRaw . ' 00:00:00';
        $until = date('Y-m-d 00:00:00', strtotime($dateRaw . ' +1 day'));

        $rows   = $this->loads->forDriverInWindow($driverId, $since, $until);
        $totals = $this->loads->totalsForDriverInWindow($driverId, $since, $until);

        // Pay-week boundaries containing the viewed date. The start day
        // is the driver's preference (account.pay_week_start_day); the
        // end is start + 6 inclusive.
        //
        // Algorithm: take date('w') of the viewed day (0=Sun..6=Sat),
        // subtract the configured start-day index, mod 7. That gives
        // the number of days to subtract to land on the most recent
        // week-start at or before the viewed date.
        $startKey = is_string($account['pay_week_start_day'] ?? null)
            ? (string) $account['pay_week_start_day']
            : 'sun';
        $startIndex = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6][$startKey] ?? 0;
        $dow        = (int) date('w', strtotime($dateRaw));
        $offsetDays = ($dow - $startIndex + 7) % 7;

        $weekStartDate = date('Y-m-d', strtotime($dateRaw . ' -' . $offsetDays . ' days'));
        $weekEndDate   = date('Y-m-d', strtotime($weekStartDate . ' +6 days'));
        $weekSince     = $weekStartDate . ' 00:00:00';
        $weekUntil     = date('Y-m-d 00:00:00', strtotime($weekStartDate . ' +7 days'));
        $weekTotals    = $this->loads->totalsForDriverInWindow($driverId, $weekSince, $weekUntil);

        $this->session->start();
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');

        // Sibling dates for the day-jump nav. We don't restrict the
        // bounds; the user can walk arbitrarily far back. forward-from-
        // -today is allowed (will show no rows) for a "tomorrow" preview.
        $prevDate = date('Y-m-d', strtotime($dateRaw . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($dateRaw . ' +1 day'));
        $today    = date('Y-m-d');

        // The driver's CURRENT effective band + shift. Shown as a pill
        // at the top of the dashboard so they know what pay parameters
        // a new load will be computed against. Per-load rows in the
        // breakdown show their own snapshotted band — those can differ
        // from this readout when the driver has crossed a band edge
        // since the load was created.
        $blob       = $this->blobBuilder->build($account);
        $blobParts  = explode('-', $blob);
        $effective  = [
            'band'  => (string) ($blobParts[0] ?? '6'),
            'shift' => (string) ($blobParts[1] ?? 'day'),
        ];

        return $this->view('dashboard/index', [
            'base'      => $request->basePath(),
            'driver'    => $account,
            'date'      => $dateRaw,
            'isToday'   => $dateRaw === $today,
            'today'     => $today,
            'prevDate'  => $prevDate,
            'nextDate'  => $nextDate,
            'rows'           => $rows,
            'totals'         => $totals,
            'csrfToken'      => $this->csrf->token(),
            'flash'          => is_string($flash) ? $flash : null,
            'effective'      => $effective,
            'weekTotals'     => $weekTotals,
            'weekStartDate'  => $weekStartDate,
            'weekEndDate'    => $weekEndDate,
        ]);
    }

    /**
     * POST /dashboard/recompute — self-serve "Refresh my pay" for the
     * signed-in driver. Scoped to the current account's driver_id, so
     * a driver can only ever touch their own rows; no admin gate
     * needed.
     *
     * The math is deterministic on stable inputs (current pay_rates +
     * pay_variables), and DriverLoad::recomputePay short-circuits
     * rows whose computed values match the stored ones — so spam-
     * clicking is harmless and no cooldown is needed.
     *
     * The window is bounded to the date the user is currently viewing
     * (or "today" if none) plus the prior 30 days, matching what they
     * actually see on the dashboard.
     */
    public function recompute(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            $this->session->put('_flash', 'Your session expired. Please try again.');
            return $this->redirect($request->basePath() . '/dashboard');
        }

        $driverId = (int) $account['id'];

        // Scope the window to the dashboard's date (defaulting to today)
        // minus 30 days, so we never silently rewrite older history a
        // driver isn't looking at.
        $dateRaw = (string) $request->input('date', '');
        $dateRaw = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) ? $dateRaw : date('Y-m-d');
        $since   = date('Y-m-d', strtotime($dateRaw . ' -30 days'));

        try {
            // Driver-initiated refresh OVERRIDES each load's snapshotted
            // variables blob with one derived from the driver's CURRENT
            // profile. That's the "I just set my hire date, fix my old
            // loads" use case — the snapshot was wrong at insert time
            // and the driver wants it corrected. The admin path stays
            // snapshot-preserving (no $applyProfile passed).
            $stats = $this->recomputer->run($driverId, $since, $account);
        } catch (\Throwable $e) {
            $this->session->put('_flash', 'Refresh failed: ' . $e->getMessage());
            return $this->redirect($request->basePath() . '/dashboard?date=' . urlencode($dateRaw));
        }

        $this->session->put('_flash', sprintf(
            'Refreshed pay (since %s): %d load(s) considered, %d updated, %d already up-to-date.',
            $since,
            $stats['considered'],
            $stats['updated'],
            $stats['unchanged'],
        ));
        return $this->redirect($request->basePath() . '/dashboard?date=' . urlencode($dateRaw));
    }
}
