<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\DriverLoad;
use PayTracker\Security\Session;

/**
 * LoadsController — read-only verification surface for the driver_loads
 * backfill.
 *
 * Same pattern as DistancesController: this is a "show me the data" page
 * rather than a full UI. Just enough to let QA confirm the backfill ran
 * and matches the legacy per-driver tables.
 */
final class LoadsController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly DriverLoad $loads,
        private readonly Session $session,
    ) {
    }

    public function index(Request $request): Response
    {
        if ($this->auth->currentAccount() === null) {
            return $this->redirect($request->basePath() . '/login');
        }

        // Optional `?driver_id=N` filter so a QA tester can spot-check one
        // driver against the legacy loadsNN table.
        $driverId = (int) ($request->input('driver_id', '0') ?? 0);

        // The load-entry controller (POST /loads) stashes a `_flash`
        // message on success and redirects here. Pop it so a refresh
        // doesn't keep re-showing the same banner.
        $this->session->start();
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');

        return $this->view('loads/index', [
            'base'         => $request->basePath(),
            'summary'      => $this->loads->summary(),
            'perDriver'    => $this->loads->countsPerDriver(50),
            'recent'       => $this->loads->recentAcrossAll(25),
            'driverFilter' => $driverId,
            'driverRows'   => $driverId > 0 ? $this->loads->forDriver($driverId, 25) : [],
            'flash'        => is_string($flash) ? $flash : null,
        ]);
    }
}
