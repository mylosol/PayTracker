<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\City;
use PayTracker\Models\CityDistance;
use PayTracker\Models\DriverLoad;
use PayTracker\Models\Terminal;
use PayTracker\Services\Pay\LoadInputs;
use PayTracker\Services\Pay\VariableBlobBuilder;
use PayTracker\Services\PayCalculator;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * LoadEntryController — modern replacement for `newload.php` +
 * `loadedmiles.php`. One submit per load (the legacy cookie-batch flow
 * is intentionally not preserved — see PR notes).
 *
 * Write flow:
 *   1. Validate inputs (allow-listed enums, charset, numeric ranges).
 *   2. Resolve miles via CityDistance::lookupOrFetch — local matrix first,
 *      Google Maps fallback with cache-fill.
 *   3. DriverLoad::insertOne writes both the typed columns AND the legacy
 *      hyphen-string blobs so unported legacy pages keep working.
 *   4. Redirect to /loads with a flash showing the assigned frtl.
 *
 * Differences from legacy (in addition to the obvious "actually writes to
 * the DB, no cookies"):
 *
 *   - One row per submit (legacy collected several in cookies until a
 *     "submit day" trigger).
 *   - CSRF check on every POST.
 *   - Driver identity comes from the session, never from a cookie or
 *     POST field. A user cannot submit a load for someone else.
 *   - Unknown city pair returns a validation error rather than silently
 *     storing a 999-mile sentinel.
 */
final class LoadEntryController extends Controller
{
    /**
     * Allowed load_type values. 0 = loaded one-way, 1 = round-trip.
     * Legacy also used "4" for an obscure case that doesn't appear in
     * fresh entries — not exposed here.
     *
     * @var list<int>
     */
    private const ALLOWED_LOAD_TYPES = [0, 1];

    /** Sanity bounds; outside this an input is rejected as a typo. */
    private const MAX_MINUTES   = 1440; // 24h cap on dem/break
    private const MAX_EXTRA_PAY = 999.99;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly City $cities,
        private readonly CityDistance $distances,
        private readonly DriverLoad $loads,
        private readonly Terminal $terminals,
        private readonly PayCalculator $calculator,
        private readonly VariableBlobBuilder $blobBuilder,
    ) {
    }

    /**
     * GET /loads/new — render the entry form.
     */
    public function create(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }

        return $this->view('loads/new', [
            'csrfToken' => $this->csrf->token(),
            'base'      => $request->basePath(),
            'cities'    => $this->cities->allForPicker(),
            'terminals' => $this->terminals->all(),
            'driver'    => $account,
            'flash'     => $this->popFlash(),
            'old'       => [
                'frtl'      => $this->session->get('_old_frtl')     ?? '',
                'pickup'    => $this->session->get('_old_pickup')   ?? '',
                'delivery'  => $this->session->get('_old_delivery') ?? '',
                'load_type' => $this->session->get('_old_type')     ?? '0',
                'dem'       => $this->session->get('_old_dem')      ?? '0',
                'break'     => $this->session->get('_old_break')    ?? '0',
                'extra'     => $this->session->get('_old_extra')    ?? '0',
                'split'     => $this->session->get('_old_split')    ?? '0',
                'weekend'   => $this->session->get('_old_weekend')  ?? '0',
            ],
        ]);
    }

    /**
     * POST /loads — validate, look up miles, insert, redirect.
     */
    public function store(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        // --- pull + preserve old input ----------------------------------
        $frtlRaw  = trim((string) $request->input('frtl', ''));
        $pickup   = trim((string) $request->input('pickup_city', ''));
        $delivery = trim((string) $request->input('delivery_city', ''));
        $typeRaw  = (string) $request->input('load_type', '');
        $splitRaw = (string) $request->input('is_split', '0');
        $wkRaw    = (string) $request->input('is_weekend', '0');
        $demRaw   = (string) $request->input('dem_minutes', '0');
        $brkRaw   = (string) $request->input('break_minutes', '0');
        $extraRaw = (string) $request->input('extra_pay', '0');
        $notes    = trim((string) $request->input('notes', ''));

        $this->session->put('_old_frtl', $frtlRaw);
        $this->session->put('_old_pickup', $pickup);
        $this->session->put('_old_delivery', $delivery);
        $this->session->put('_old_type', $typeRaw);
        $this->session->put('_old_dem', $demRaw);
        $this->session->put('_old_break', $brkRaw);
        $this->session->put('_old_extra', $extraRaw);
        $this->session->put('_old_split', $splitRaw);
        $this->session->put('_old_weekend', $wkRaw);

        // --- validate ---------------------------------------------------
        // FRTL is the driver's dispatch number — typed in from paperwork
        // when available. It's OPTIONAL: a driver who doesn't have the
        // number handy can leave it blank and we'll auto-assign MAX+1
        // for their account in DriverLoad::insertOne. When supplied,
        // it must be a positive int unique per driver.
        $frtl = 0; // 0 = "let the model auto-assign"
        if ($frtlRaw !== '') {
            if (! ctype_digit($frtlRaw) || (int) $frtlRaw <= 0) {
                return $this->failBack($request, 'FRTL must be a positive number, or left blank to auto-assign.');
            }
            $frtl = (int) $frtlRaw;
            if ($frtl > 2147483647) {
                return $this->failBack($request, 'FRTL is too large to be valid.');
            }
            if ($this->loads->frtlExists((int) $account['id'], $frtl)) {
                return $this->failBack($request, sprintf('FRTL %d is already on file for this driver.', $frtl));
            }
        }

        if ($pickup === '' || $delivery === '') {
            return $this->failBack($request, 'Pick-up and delivery cities are required.');
        }
        if ($pickup === $delivery) {
            return $this->failBack($request, 'Pick-up and delivery cannot be the same city.');
        }
        if (! is_numeric($typeRaw) || ! in_array((int) $typeRaw, self::ALLOWED_LOAD_TYPES, true)) {
            return $this->failBack($request, 'Load type must be loaded one-way or round-trip.');
        }
        $loadType = (int) $typeRaw;

        $isSplit   = $splitRaw === '1' ? 1 : 0;
        $isWeekend = $wkRaw === '1' ? 1 : 0;

        if (! is_numeric($demRaw) || (int) $demRaw < 0 || (int) $demRaw > self::MAX_MINUTES) {
            return $this->failBack($request, 'Demurrage minutes must be between 0 and ' . self::MAX_MINUTES . '.');
        }
        if (! is_numeric($brkRaw) || (int) $brkRaw < 0 || (int) $brkRaw > self::MAX_MINUTES) {
            return $this->failBack($request, 'Breakdown minutes must be between 0 and ' . self::MAX_MINUTES . '.');
        }
        if (! is_numeric($extraRaw) || (float) $extraRaw < 0 || (float) $extraRaw > self::MAX_EXTRA_PAY) {
            return $this->failBack($request, 'Extra pay must be between 0 and ' . self::MAX_EXTRA_PAY . '.');
        }

        // Pick-up MUST be a known terminal — drivers fuel at terminals and
        // load there. This is a stricter check than "is this a known city"
        // because the city list is much larger than the terminal list.
        if (! $this->terminals->isKnown($pickup)) {
            return $this->failBack($request, sprintf('Pick-up "%s" is not a known terminal. Pick from the list.', $pickup));
        }

        // Delivery can be any city the matrix knows about. The add-city
        // flow is the proper way to introduce a new one.
        if ($this->cities->findByName($delivery) === null) {
            return $this->failBack($request, sprintf('Delivery city "%s" is not in the city list. Add it first.', $delivery));
        }

        // --- mile lookup -----------------------------------------------
        $milesBefore = $this->distances->between($pickup, $delivery);
        $emptyMiles  = $this->distances->lookupOrFetch($pickup, $delivery);
        if ($emptyMiles === null) {
            return $this->failBack(
                $request,
                sprintf(
                    'Could not find a mileage for %s → %s, and Google Maps could not resolve it either.',
                    $pickup,
                    $delivery
                )
            );
        }
        $usedGoogleMaps = count($milesBefore) === 0 ? 1 : 0;

        // --- compute pay -------------------------------------------------
        // Run PayCalculator now so the dashboard's totals are accurate
        // immediately. Loads inserted with np=0 would surface the
        // dashboard's "ask admin to recompute" stale banner; computing
        // here avoids that for the everyday flow. The admin recompute
        // path (/pay-admin/recompute) still exists for the bulk
        // "rates changed, replay history" case.
        // Naming gotcha: $emptyMiles in this controller is actually the
        // resolved pickup→delivery distance, not the empty-return leg
        // (it's the value CityDistance returned). For the calculator we
        // pass it as load_miles (the loaded leg) and set empty_miles=0
        // because the modern form has no separate empty-return field.
        // Drivers expecting empty pay on a one-way should pick Round-trip
        // instead; that path uses the round-trip rate table without
        // double-billing.
        // Build the variables blob from the driver's profile (hire_date
        // → tenure band, shift → night-bonus toggle). When hire_date is
        // unset the builder falls back to "6-day--0" (junior floor, day
        // shift) — the under-pay side of the line, which we prefer over
        // the historical "168-night--0" hardcode that inflated pay for
        // every driver regardless of tenure or shift.
        $variablesBlob = $this->blobBuilder->build($account);

        $payInput = new LoadInputs(
            load_type:          $loadType,
            load_miles:         $emptyMiles,
            empty_miles:        0,
            begin_empty_miles:  0,
            is_split:           $isSplit,
            is_weekend:         $isWeekend,
            extra_pay:          (float) $extraRaw,
            dem_minutes:        (int) $demRaw,
            break_minutes:      (int) $brkRaw,
            variables_blob:     $variablesBlob,
            out_of_route_ind:   0,
            out_of_route_miles: 0,
            terminal_pcola:     0,
        );
        $pay = $this->calculator->computeFor($payInput);

        // --- insert -----------------------------------------------------
        $frtl = $this->loads->insertOne([
            'driver_id'          => (int) $account['id'],
            'frtl'               => $frtl,
            'load_type'          => $loadType,
            'pickup_city'        => $pickup,
            'delivery_city'      => $delivery,
            'empty_miles'        => $emptyMiles,
            'begin_empty_miles'  => 0,
            'is_split'           => $isSplit,
            'is_weekend'         => $isWeekend,
            'extra_pay'          => (float) $extraRaw,
            'dem_minutes'        => (int) $demRaw,
            'break_minutes'      => (int) $brkRaw,
            'out_of_route_ind'   => 0,
            'out_of_route_miles' => 0,
            'used_google_maps'   => $usedGoogleMaps,
            'terminal_pcola'     => 0,
            'notes'              => $notes !== '' ? $notes : null,
            'np'                 => $pay['np'],
            'op'                 => $pay['op'],
            'variables'          => $variablesBlob,
        ]);

        // Clear preserved input on success.
        foreach (['_old_frtl', '_old_pickup', '_old_delivery', '_old_type', '_old_dem', '_old_break', '_old_extra', '_old_split', '_old_weekend'] as $k) {
            $this->session->forget($k);
        }

        $sourceNote = $usedGoogleMaps ? ' (via Google Maps, now cached)' : '';
        $frtlNote   = $frtlRaw === '' ? ' (auto-assigned)' : '';
        $this->session->put('_flash', sprintf(
            'Added load frtl=%d%s: %s → %s, %d miles%s. Pay: $%s.',
            $frtl,
            $frtlNote,
            $pickup,
            $delivery,
            $emptyMiles,
            $sourceNote,
            number_format($pay['np'], 2)
        ));
        return $this->redirect($request->basePath() . '/loads');
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/loads/new');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
