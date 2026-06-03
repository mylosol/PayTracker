<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\CityDistance;

/**
 * DistancesController — read-only verification surface for the
 * `city_distances` backfill migration.
 *
 * This is intentionally a "show me the data" page rather than a full UI.
 * Writes will land in a future branch that redirects the add-location flow
 * to populate this table; for now QA needs only enough to confirm the
 * backfill ran and the row counts look plausible.
 *
 * Two surfaces:
 *   GET /distances              — dashboard: counters + sample table
 *   GET /distances?from=X&to=Y  — same page with a lookup result for the
 *                                  given pair
 */
final class DistancesController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly CityDistance $distances,
    ) {
    }

    public function index(Request $request): Response
    {
        if ($this->auth->currentAccount() === null) {
            return $this->redirect($request->basePath() . '/login');
        }

        $from = trim((string) $request->input('from', ''));
        $to   = trim((string) $request->input('to', ''));
        $lookup = ($from !== '' && $to !== '')
            ? $this->distances->between($from, $to)
            : null;

        return $this->view('distances/index', [
            'base'    => $request->basePath(),
            'summary' => $this->distances->summary(),
            'sample'  => $this->distances->sample(25),
            'from'    => $from,
            'to'      => $to,
            'lookup'  => $lookup,
        ]);
    }
}
