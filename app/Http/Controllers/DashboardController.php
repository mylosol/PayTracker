<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\DriverLoad;

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

        // Sibling dates for the day-jump nav. We don't restrict the
        // bounds; the user can walk arbitrarily far back. forward-from-
        // -today is allowed (will show no rows) for a "tomorrow" preview.
        $prevDate = date('Y-m-d', strtotime($dateRaw . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($dateRaw . ' +1 day'));
        $today    = date('Y-m-d');

        return $this->view('dashboard/index', [
            'base'      => $request->basePath(),
            'driver'    => $account,
            'date'      => $dateRaw,
            'isToday'   => $dateRaw === $today,
            'today'     => $today,
            'prevDate'  => $prevDate,
            'nextDate'  => $nextDate,
            'rows'      => $rows,
            'totals'    => $totals,
        ]);
    }
}
