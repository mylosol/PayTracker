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
    private const MAX_MILES     = 9999; // typo guard for begin-empty / out-of-route

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
            'beTerminals' => $this->terminals->listActive(),
            'driver'    => $account,
            'flash'     => $this->popFlash(),
            'mode'      => 'create',
            'editFrtl'  => 0,
            'old'       => [
                'frtl'              => $this->session->get('_old_frtl')              ?? '',
                'pickup'            => $this->session->get('_old_pickup')            ?? '',
                'delivery'          => $this->session->get('_old_delivery')          ?? '',
                'load_type'         => $this->session->get('_old_type')              ?? '0',
                'dem'               => $this->session->get('_old_dem')               ?? '0',
                'break'             => $this->session->get('_old_break')             ?? '0',
                'extra'             => $this->session->get('_old_extra')             ?? '0',
                'split'             => $this->session->get('_old_split')             ?? '0',
                'weekend'           => $this->session->get('_old_weekend')           ?? '0',
                'end_empty'         => $this->session->get('_old_end_empty')         ?? '',
                'date'              => $this->session->get('_old_date')              ?? '',
                'begin_empty_miles' => $this->session->get('_old_begin_empty_miles') ?? '0',
                'out_of_route_miles'=> $this->session->get('_old_out_of_route_miles')?? '0',
                'notes'             => '',
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
        $frtlRaw   = trim((string) $request->input('frtl', ''));
        $pickup    = trim((string) $request->input('pickup_city', ''));
        $delivery  = trim((string) $request->input('delivery_city', ''));
        $endEmpty  = trim((string) $request->input('end_empty_city', ''));
        $typeRaw   = (string) $request->input('load_type', '');
        $splitRaw  = (string) $request->input('is_split', '0');
        $wkRaw     = (string) $request->input('is_weekend', '0');
        $demRaw    = (string) $request->input('dem_minutes', '0');
        $brkRaw    = (string) $request->input('break_minutes', '0');
        $extraRaw  = (string) $request->input('extra_pay', '0');
        $dateRaw   = trim((string) $request->input('load_date', ''));
        $beginEmptyRaw = (string) $request->input('begin_empty_miles', '0');
        $outOfRouteRaw = (string) $request->input('out_of_route_miles', '0');
        $notes     = trim((string) $request->input('notes', ''));

        $this->session->put('_old_frtl', $frtlRaw);
        $this->session->put('_old_pickup', $pickup);
        $this->session->put('_old_delivery', $delivery);
        $this->session->put('_old_end_empty', $endEmpty);
        $this->session->put('_old_type', $typeRaw);
        $this->session->put('_old_dem', $demRaw);
        $this->session->put('_old_break', $brkRaw);
        $this->session->put('_old_extra', $extraRaw);
        $this->session->put('_old_split', $splitRaw);
        $this->session->put('_old_weekend', $wkRaw);
        $this->session->put('_old_date', $dateRaw);
        $this->session->put('_old_begin_empty_miles', $beginEmptyRaw);
        $this->session->put('_old_out_of_route_miles', $outOfRouteRaw);

        // --- validate ---------------------------------------------------
        // FRTL is the driver's dispatch number — typed in from paperwork.
        // It is REQUIRED for any save-to-DB submission: it's the PK
        // alongside driver_id and the legacy reconciliation flow has no
        // way to match a load back to dispatch records without it. A
        // driver who doesn't have the paperwork in hand should leave the
        // "Store Load Info" checkbox OFF and the load lives in the
        // browser's localStorage scratchpad (see /loads/preview).
        if ($frtlRaw === '') {
            return $this->failBack($request, 'FRTL # is required to save a load. To enter a load without one, turn off "Store Load Info" at the top of the form.');
        }
        if (! ctype_digit($frtlRaw) || (int) $frtlRaw <= 0) {
            return $this->failBack($request, 'FRTL must be a positive number.');
        }
        $frtl = (int) $frtlRaw;
        if ($frtl > 2147483647) {
            return $this->failBack($request, 'FRTL is too large to be valid.');
        }
        if ($this->loads->frtlExists((int) $account['id'], $frtl)) {
            return $this->failBack($request, sprintf('FRTL %d is already on file for this driver.', $frtl));
        }

        $assembled = $this->assembleLoadContext($request, $account);
        if (! $assembled['ok']) {
            return $this->failBack($request, $assembled['error']);
        }
        $ctx             = $assembled['data'];
        $pickup          = $ctx['pickup_city'];
        $delivery        = $ctx['delivery_city'];
        $endEmpty        = $ctx['end_empty_city_raw'];
        $loadType        = $ctx['load_type'];
        $isSplit         = $ctx['is_split'];
        $isWeekend       = $ctx['is_weekend'];
        $emptyMiles      = $ctx['empty_miles'];
        $endEmptyMiles   = $ctx['end_empty_miles'];
        $beginEmptyMiles = $ctx['begin_empty_miles'];
        $outOfRouteMiles = $ctx['out_of_route_miles'];
        $outOfRouteInd   = $ctx['out_of_route_ind'];
        $usedGoogleMaps  = $ctx['used_google_maps'];
        $loadDate        = $ctx['date'];
        $variablesBlob   = $ctx['variables'];
        $pay             = $ctx['pay_breakdown'];

        // --- insert -----------------------------------------------------
        $frtl = $this->loads->insertOne([
            'driver_id'          => (int) $account['id'],
            'frtl'               => $frtl,
            'date'               => $loadDate,
            'load_type'          => $loadType,
            'pickup_city'        => $pickup,
            'delivery_city'      => $delivery,
            'end_empty_city'     => $loadType === 0 && $endEmpty !== '' ? $endEmpty : null,
            'end_empty_miles'    => $endEmptyMiles,
            'empty_miles'        => $emptyMiles,
            'begin_empty_miles'  => $beginEmptyMiles,
            'is_split'           => $isSplit,
            'is_weekend'         => $isWeekend,
            'extra_pay'          => (float) $extraRaw,
            'dem_minutes'        => (int) $demRaw,
            'break_minutes'      => (int) $brkRaw,
            'out_of_route_ind'   => $outOfRouteInd,
            'out_of_route_miles' => $outOfRouteMiles,
            'used_google_maps'   => $usedGoogleMaps,
            'notes'              => $notes !== '' ? $notes : null,
            'np'                 => $pay['np'],
            'op'                 => $pay['op'],
            'variables'          => $variablesBlob,
            'pay_breakdown'      => $pay,
        ]);

        // Clear preserved input on success.
        foreach (['_old_frtl', '_old_pickup', '_old_delivery', '_old_end_empty', '_old_type', '_old_dem', '_old_break', '_old_extra', '_old_split', '_old_weekend', '_old_date', '_old_begin_empty_miles', '_old_out_of_route_miles'] as $k) {
            $this->session->forget($k);
        }

        $sourceNote = $usedGoogleMaps ? ' (via Google Maps, now cached)' : '';
        $this->session->put('_flash', sprintf(
            'Added load frtl=%d: %s → %s, %d miles%s. Pay: $%s.',
            $frtl,
            $pickup,
            $delivery,
            $emptyMiles,
            $sourceNote,
            number_format($pay['np'], 2)
        ));
        // /loads is now Super-Admin-only (diagnostic surface). Drivers
        // land on their dashboard, where the new row + pay totals are
        // already visible — matches the user's mental model better
        // than the legacy "back to the list" pattern anyway.
        return $this->redirect($request->basePath() . '/dashboard');
    }

    /**
     * GET /loads/{frtl}/edit — render the entry form pre-populated
     * with this load's stored values. Scoped to the signed-in driver
     * — passing another driver's frtl returns a 404-flavoured flash
     * rather than leaking the row.
     */
    public function edit(Request $request, string $frtl): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        if (! ctype_digit($frtl) || (int) $frtl <= 0) {
            $this->session->put('_flash', 'That FRTL is not valid.');
            return $this->redirect($request->basePath() . '/dashboard');
        }
        $frtlInt = (int) $frtl;
        $row     = $this->loads->findForDriver((int) $account['id'], $frtlInt);
        if ($row === null) {
            $this->session->put('_flash', sprintf('Load %d not found on your account.', $frtlInt));
            return $this->redirect($request->basePath() . '/dashboard');
        }

        return $this->view('loads/new', [
            'csrfToken' => $this->csrf->token(),
            'base'      => $request->basePath(),
            'cities'    => $this->cities->allForPicker(),
            'terminals' => $this->terminals->all(),
            'beTerminals' => $this->terminals->listActive(),
            'driver'    => $account,
            'flash'     => $this->popFlash(),
            'mode'      => 'edit',
            'editFrtl'  => $frtlInt,
            'old'       => [
                'frtl'               => (string) $frtlInt,
                'pickup'             => (string) ($row['pickup_city']         ?? ''),
                'delivery'           => (string) ($row['delivery_city']       ?? ''),
                'end_empty'          => (string) ($row['end_empty_city']      ?? ''),
                'load_type'          => (string) ($row['load_type']           ?? '0'),
                'dem'                => (string) ($row['dem_minutes']         ?? '0'),
                'break'              => (string) ($row['break_minutes']       ?? '0'),
                'extra'              => (string) ($row['extra_pay']           ?? '0'),
                'split'              => (string) ($row['is_split']            ?? '0'),
                'weekend'            => (string) ($row['is_weekend']          ?? '0'),
                // Drop the datetime's time portion for the <input type="date">.
                'date'               => substr((string) ($row['date'] ?? ''), 0, 10),
                'begin_empty_miles'  => (string) ($row['begin_empty_miles']   ?? '0'),
                'out_of_route_miles' => (string) ($row['out_of_route_miles']  ?? '0'),
                'notes'              => (string) ($row['notes']               ?? ''),
            ],
        ]);
    }

    /**
     * POST /loads/{frtl} — validate, look up miles (using the
     * potentially-changed cities), recompute pay, UPDATE the row.
     * Driver-scoped: a malicious POST with someone else's frtl
     * silently fails because the WHERE clause in updateOne includes
     * the driver_id.
     */
    public function update(Request $request, string $frtl): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBackEdit($request, $frtl, 'Your session expired. Please try again.');
        }
        if (! ctype_digit($frtl) || (int) $frtl <= 0) {
            $this->session->put('_flash', 'That FRTL is not valid.');
            return $this->redirect($request->basePath() . '/dashboard');
        }
        $frtlInt = (int) $frtl;
        $existing = $this->loads->findForDriver((int) $account['id'], $frtlInt);
        if ($existing === null) {
            $this->session->put('_flash', sprintf('Load %d not found on your account.', $frtlInt));
            return $this->redirect($request->basePath() . '/dashboard');
        }

        $pickup    = trim((string) $request->input('pickup_city', ''));
        $delivery  = trim((string) $request->input('delivery_city', ''));
        $endEmpty  = trim((string) $request->input('end_empty_city', ''));
        $typeRaw   = (string) $request->input('load_type', '');
        $splitRaw  = (string) $request->input('is_split', '0');
        $wkRaw     = (string) $request->input('is_weekend', '0');
        $demRaw    = (string) $request->input('dem_minutes', '0');
        $brkRaw    = (string) $request->input('break_minutes', '0');
        $extraRaw  = (string) $request->input('extra_pay', '0');
        $dateRaw   = trim((string) $request->input('load_date', ''));
        $beginEmptyRaw = (string) $request->input('begin_empty_miles', '0');
        $outOfRouteRaw = (string) $request->input('out_of_route_miles', '0');
        $notes     = trim((string) $request->input('notes', ''));

        if ($pickup === '' || $delivery === '') {
            return $this->failBackEdit($request, $frtl, 'Pick-up and delivery cities are required.');
        }
        if ($pickup === $delivery) {
            return $this->failBackEdit($request, $frtl, 'Pick-up and delivery cannot be the same city.');
        }
        if (! is_numeric($typeRaw) || ! in_array((int) $typeRaw, self::ALLOWED_LOAD_TYPES, true)) {
            return $this->failBackEdit($request, $frtl, 'Load type must be loaded one-way or round-trip.');
        }
        if (! $this->terminals->isKnown($pickup)) {
            return $this->failBackEdit($request, $frtl, sprintf('Pick-up "%s" is not a known terminal. Pick from the list.', $pickup));
        }
        if ($this->cities->findByName($delivery) === null) {
            return $this->failBackEdit($request, $frtl, sprintf('Delivery city "%s" is not in the city list. Add it first.', $delivery));
        }
        if ($endEmpty !== '' && $this->cities->findByName($endEmpty) === null) {
            return $this->failBackEdit($request, $frtl, sprintf('End Empty "%s" is not in the city list.', $endEmpty));
        }
        if (! is_numeric($demRaw) || (int) $demRaw < 0 || (int) $demRaw > self::MAX_MINUTES) {
            return $this->failBackEdit($request, $frtl, 'Demurrage minutes must be between 0 and ' . self::MAX_MINUTES . '.');
        }
        if (! is_numeric($brkRaw) || (int) $brkRaw < 0 || (int) $brkRaw > self::MAX_MINUTES) {
            return $this->failBackEdit($request, $frtl, 'Breakdown minutes must be between 0 and ' . self::MAX_MINUTES . '.');
        }
        if (! is_numeric($extraRaw) || (float) $extraRaw < 0 || (float) $extraRaw > self::MAX_EXTRA_PAY) {
            return $this->failBackEdit($request, $frtl, 'Extra pay must be between 0 and ' . self::MAX_EXTRA_PAY . '.');
        }
        if ($dateRaw === '') {
            // Empty edit POST means "keep existing date" — fall through to
            // the existing row's value so we never silently overwrite the
            // historical timestamp with today.
            $dateRaw = substr((string) ($existing['date'] ?? date('Y-m-d')), 0, 10);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) !== 1) {
            return $this->failBackEdit($request, $frtl, 'Load date must be in YYYY-MM-DD format.');
        }
        $parsedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $dateRaw) {
            return $this->failBackEdit($request, $frtl, 'Load date is not a valid calendar date.');
        }
        if ($parsedDate > new \DateTimeImmutable('tomorrow')) {
            return $this->failBackEdit($request, $frtl, 'Load date cannot be in the future.');
        }
        $loadDate = $dateRaw . ' 00:00:00';

        if (! is_numeric($beginEmptyRaw) || (int) $beginEmptyRaw < 0 || (int) $beginEmptyRaw > self::MAX_MILES) {
            return $this->failBackEdit($request, $frtl, 'Begin empty miles must be between 0 and ' . self::MAX_MILES . '.');
        }
        if (! is_numeric($outOfRouteRaw) || (int) $outOfRouteRaw < 0 || (int) $outOfRouteRaw > self::MAX_MILES) {
            return $this->failBackEdit($request, $frtl, 'Out-of-route miles must be between 0 and ' . self::MAX_MILES . '.');
        }
        $beginEmptyMiles = (int) $beginEmptyRaw;
        $outOfRouteMiles = (int) $outOfRouteRaw;
        // Round-trip loads don't have a separate empty pre-leg in the
        // legacy formula — the return is implicit in the round-trip
        // rate. Silently drop a stray begin-empty value so a driver
        // who toggled between types after typing doesn't accidentally
        // get paid for an empty leg the formula doesn't expect.
        // (Mirrors the same one-way-only guard on End Empty.)
        if ((int) $typeRaw !== 0) {
            $beginEmptyMiles = 0;
        }
        $outOfRouteInd   = $outOfRouteMiles > 0 ? 1 : 0;

        $loadType  = (int) $typeRaw;
        $isSplit   = $splitRaw === '1' ? 1 : 0;
        $isWeekend = $wkRaw === '1' ? 1 : 0;

        $miles = $this->distances->lookupOrFetch($pickup, $delivery);
        if ($miles === null) {
            return $this->failBackEdit(
                $request,
                $frtl,
                sprintf('Could not find a mileage for %s → %s.', $pickup, $delivery)
            );
        }
        // End-empty leg: only meaningful for one-way. Round-trip
        // submissions with End Empty filled in get the value silently
        // dropped (legacy behaviour) so the form's End-Empty value
        // doesn't mysteriously vanish without explanation.
        $endEmptyMiles = 0;
        if ($loadType === 0 && $endEmpty !== '') {
            if ($endEmpty === $delivery) {
                return $this->failBackEdit($request, $frtl, 'End Empty must differ from the delivery city.');
            }
            $resolved = $this->distances->lookupOrFetch($delivery, $endEmpty);
            if ($resolved === null) {
                return $this->failBackEdit(
                    $request,
                    $frtl,
                    sprintf('Could not find an empty-leg mileage for %s → %s.', $delivery, $endEmpty)
                );
            }
            $endEmptyMiles = $resolved;
        }

        // Recompute pay using the driver's CURRENT profile. Consistent
        // with the Refresh-my-pay path: if a driver's tenure/shift has
        // moved since the original load was entered, the edit
        // recomputes against today's truth.
        $variablesBlob = $this->blobBuilder->build($account);
        $payInput = new LoadInputs(
            load_type:          $loadType,
            load_miles:         $miles,
            empty_miles:        $endEmptyMiles,
            begin_empty_miles:  $beginEmptyMiles,
            is_split:           $isSplit,
            is_weekend:         $isWeekend,
            extra_pay:          (float) $extraRaw,
            dem_minutes:        (int) $demRaw,
            break_minutes:      (int) $brkRaw,
            variables_blob:     $variablesBlob,
            out_of_route_ind:   $outOfRouteInd,
            out_of_route_miles: $outOfRouteMiles,
        );
        $pay = $this->calculator->computeFor($payInput);

        try {
            $this->loads->updateOne((int) $account['id'], $frtlInt, [
                'load_type'         => $loadType,
                'date'              => $loadDate,
                'pickup_city'       => $pickup,
                'delivery_city'     => $delivery,
                'end_empty_city'    => $loadType === 0 && $endEmpty !== '' ? $endEmpty : null,
                'end_empty_miles'   => $endEmptyMiles,
                'empty_miles'       => $miles,
                'begin_empty_miles' => $beginEmptyMiles,
                'is_split'          => $isSplit,
                'is_weekend'        => $isWeekend,
                'extra_pay'         => (float) $extraRaw,
                'dem_minutes'       => (int) $demRaw,
                'break_minutes'     => (int) $brkRaw,
                'out_of_route_ind'  => $outOfRouteInd,
                'out_of_route_miles'=> $outOfRouteMiles,
                'notes'             => $notes !== '' ? $notes : null,
                'np'                => $pay['np'],
                'op'                => $pay['op'],
                'variables'         => $variablesBlob,
                'pay_breakdown'     => $pay,
            ]);
        } catch (\Throwable $e) {
            return $this->failBackEdit($request, $frtl, 'Could not save load: ' . $e->getMessage());
        }

        $this->session->put('_flash', sprintf(
            'Updated load frtl=%d: %s → %s, %d miles. Pay: $%s.',
            $frtlInt, $pickup, $delivery, $miles, number_format($pay['np'], 2)
        ));
        return $this->redirect($request->basePath() . '/dashboard');
    }

    /**
     * POST /loads/{frtl}/delete — driver-scoped delete. The (driver_id,
     * frtl) clause in the DELETE means a malicious POST with someone
     * else's frtl no-ops silently.
     */
    public function destroy(Request $request, string $frtl): Response
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
        if (! ctype_digit($frtl) || (int) $frtl <= 0) {
            $this->session->put('_flash', 'That FRTL is not valid.');
            return $this->redirect($request->basePath() . '/dashboard');
        }
        $frtlInt = (int) $frtl;

        try {
            $deleted = $this->loads->deleteOne((int) $account['id'], $frtlInt);
        } catch (\Throwable $e) {
            $this->session->put('_flash', 'Could not delete load: ' . $e->getMessage());
            return $this->redirect($request->basePath() . '/dashboard');
        }
        $this->session->put('_flash', $deleted
            ? sprintf('Deleted load frtl=%d.', $frtlInt)
            : sprintf('Load %d was not on your account; nothing to delete.', $frtlInt)
        );
        return $this->redirect($request->basePath() . '/dashboard');
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/loads/new');
    }

    private function failBackEdit(Request $request, string $frtl, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/loads/' . urlencode($frtl) . '/edit');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }

    /**
     * POST /loads/preview — XHR endpoint backing the localStorage
     * scratchpad path. Runs the same validation + mile-lookup +
     * PayCalculator pipeline as store(), but writes nothing to the
     * database. Returns the computed row as JSON for the client to
     * stash in localStorage.
     *
     * Authoritative pay math stays on the server: the JS module never
     * implements the formula, only sums precomputed np/op figures it
     * receives from this endpoint. That guarantees "Store" and
     * "don't store" loads produce identical pay numbers.
     *
     * Response shape:
     *   { ok: true,  computed: {...row fields incl np/op/breakdown...} }
     *   { ok: false, error: "human-friendly message" }
     */
    public function preview(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Please reload and sign in again.'], 401);
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Please reload the page.'], 419);
        }

        $assembled = $this->assembleLoadContext($request, $account);
        if (! $assembled['ok']) {
            return $this->json(['ok' => false, 'error' => $assembled['error']]);
        }
        $ctx = $assembled['data'];

        // Slim the response: client only needs display-relevant fields
        // plus the breakdown for dashboard hydration. Internal blobs
        // (`variables`, `loadinfo`) are server-only and never travel
        // back to a future save (the convert-to-saved flow re-submits
        // through /loads which rebuilds the blob from current profile).
        return $this->json([
            'ok'       => true,
            'computed' => [
                'date'                 => substr((string) $ctx['date'], 0, 10),
                'load_type'            => $ctx['load_type'],
                'pickup_city'          => $ctx['pickup_city'],
                'delivery_city'        => $ctx['delivery_city'],
                'end_empty_city'       => $ctx['end_empty_city'],
                'end_empty_miles'      => $ctx['end_empty_miles'],
                'empty_miles'          => $ctx['empty_miles'],
                'begin_empty_miles'    => $ctx['begin_empty_miles'],
                'is_split'             => $ctx['is_split'],
                'is_weekend'           => $ctx['is_weekend'],
                'extra_pay'            => $ctx['extra_pay'],
                'dem_minutes'          => $ctx['dem_minutes'],
                'break_minutes'        => $ctx['break_minutes'],
                'out_of_route_miles'   => $ctx['out_of_route_miles'],
                'notes'                => $ctx['notes'],
                'np'                   => (float) $ctx['pay_breakdown']['np'],
                'op'                   => (float) $ctx['pay_breakdown']['op'],
                'pay_breakdown'        => $ctx['pay_breakdown'],
                'used_google_maps'     => $ctx['used_google_maps'],
            ],
        ]);
    }

    /**
     * POST /loads/preview-be-miles — resolve Begin Empty miles from a
     * selected terminal to the load's pick-up city. Tiny JSON endpoint
     * the load form calls when the driver picks a terminal from the
     * BE dropdown so they don't have to type miles by hand.
     *
     * Inputs (POST):
     *   _csrf
     *   begin_empty_terminal_id  — id from the `terminals` table
     *   pickup_city              — the load's pickup terminal name
     *                              (same value as the pickup_city select)
     *
     * Response shape:
     *   { ok: true,  miles: int, source: "cache"|"maps", terminal: string }
     *   { ok: false, error: "human-friendly message" }
     *
     * Failure modes (all return ok:false so the JS can re-enable the
     * manual miles input):
     *   - invalid / missing inputs
     *   - terminal id not active in the picker
     *   - distance lookup miss AND Google Maps unconfigured/failing
     *
     * Distance source priority matches the existing pay-compute path —
     * city_distances cache first (legacy matrices win), Google Maps
     * fill-fallback second. A successful Google lookup inserts the
     * pair into the cache so subsequent requests hit instantly.
     */
    public function previewBeMiles(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Please reload and sign in again.'], 401);
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->json(['ok' => false, 'error' => 'Your session expired. Please reload the page.'], 419);
        }

        $idRaw  = trim((string) $request->input('begin_empty_terminal_id', ''));
        $pickup = trim((string) $request->input('pickup_city', ''));

        if ($idRaw === '' || ! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            return $this->json(['ok' => false, 'error' => 'Pick a Begin Empty terminal first.']);
        }
        if ($pickup === '') {
            return $this->json(['ok' => false, 'error' => 'Pick a pick-up terminal first so we know where you\'re going.']);
        }

        $terminal = $this->terminals->findById((int) $idRaw);
        if ($terminal === null || $terminal['active'] !== 1) {
            return $this->json(['ok' => false, 'error' => 'That Begin Empty terminal is no longer active. Pick another, or enter miles manually.']);
        }
        if ($terminal['name'] === $pickup) {
            // Same terminal start + pickup -> 0 miles. Skip the lookup
            // entirely so the cache doesn't get a 0-mile self-pair.
            return $this->json([
                'ok'       => true,
                'miles'    => 0,
                'source'   => 'cache',
                'terminal' => $terminal['name'],
            ]);
        }

        // Track whether the lookup hit the cache or had to fall through
        // to Google Maps. CityDistance::lookupOrFetch doesn't return
        // that detail, so we peek at the cache directly first to
        // classify the source. The peek is cheap (already an indexed
        // read) and lets the UI tell the driver whether the number
        // they just got was instant from cache or freshly resolved.
        $rows   = $this->distances->between($terminal['name'], $pickup);
        $source = count($rows) > 0 ? 'cache' : 'maps';

        $miles = $this->distances->lookupOrFetch($terminal['name'], $pickup);
        if ($miles === null) {
            return $this->json([
                'ok'    => false,
                'error' => sprintf(
                    'Could not resolve miles from %s to %s. Enter the miles manually.',
                    $terminal['name'],
                    $pickup
                ),
            ]);
        }

        return $this->json([
            'ok'       => true,
            'miles'    => $miles,
            'source'   => $source,
            'terminal' => $terminal['name'],
        ]);
    }

    /**
     * Shared validation + mile-lookup + pay-compute pipeline.
     *
     * Reads everything-but-FRTL from the request, validates ranges
     * and types, resolves both pickup→delivery and (when one-way)
     * delivery→end-empty mileages via CityDistance::lookupOrFetch,
     * builds the variables blob from the driver profile, and runs
     * PayCalculator. Returns the populated context dict or the
     * first validation error.
     *
     * Pure: does not touch the DB except via reads, never inserts.
     *
     * @param array<string,mixed> $account
     * @return array{ok:true, data:array<string,mixed>}|array{ok:false, error:string}
     */
    private function assembleLoadContext(Request $request, array $account): array
    {
        $pickup    = trim((string) $request->input('pickup_city', ''));
        $delivery  = trim((string) $request->input('delivery_city', ''));
        $endEmpty  = trim((string) $request->input('end_empty_city', ''));
        $typeRaw   = (string) $request->input('load_type', '');
        $splitRaw  = (string) $request->input('is_split', '0');
        $wkRaw     = (string) $request->input('is_weekend', '0');
        $demRaw    = (string) $request->input('dem_minutes', '0');
        $brkRaw    = (string) $request->input('break_minutes', '0');
        $extraRaw  = (string) $request->input('extra_pay', '0');
        $dateRaw   = trim((string) $request->input('load_date', ''));
        $beginEmptyRaw = (string) $request->input('begin_empty_miles', '0');
        $outOfRouteRaw = (string) $request->input('out_of_route_miles', '0');
        $notes     = trim((string) $request->input('notes', ''));

        if ($pickup === '' || $delivery === '') {
            return ['ok' => false, 'error' => 'Pick-up and delivery cities are required.'];
        }
        if ($pickup === $delivery) {
            return ['ok' => false, 'error' => 'Pick-up and delivery cannot be the same city.'];
        }
        if (! is_numeric($typeRaw) || ! in_array((int) $typeRaw, self::ALLOWED_LOAD_TYPES, true)) {
            return ['ok' => false, 'error' => 'Load type must be loaded one-way or round-trip.'];
        }
        $loadType  = (int) $typeRaw;
        $isSplit   = $splitRaw === '1' ? 1 : 0;
        $isWeekend = $wkRaw === '1' ? 1 : 0;

        if (! is_numeric($demRaw) || (int) $demRaw < 0 || (int) $demRaw > self::MAX_MINUTES) {
            return ['ok' => false, 'error' => 'Demurrage minutes must be between 0 and ' . self::MAX_MINUTES . '.'];
        }
        if (! is_numeric($brkRaw) || (int) $brkRaw < 0 || (int) $brkRaw > self::MAX_MINUTES) {
            return ['ok' => false, 'error' => 'Breakdown minutes must be between 0 and ' . self::MAX_MINUTES . '.'];
        }
        if (! is_numeric($extraRaw) || (float) $extraRaw < 0 || (float) $extraRaw > self::MAX_EXTRA_PAY) {
            return ['ok' => false, 'error' => 'Extra pay must be between 0 and ' . self::MAX_EXTRA_PAY . '.'];
        }

        if ($dateRaw === '') {
            $dateRaw = date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) !== 1) {
            return ['ok' => false, 'error' => 'Load date must be in YYYY-MM-DD format.'];
        }
        $parsedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $dateRaw) {
            return ['ok' => false, 'error' => 'Load date is not a valid calendar date.'];
        }
        if ($parsedDate > new \DateTimeImmutable('tomorrow')) {
            return ['ok' => false, 'error' => 'Load date cannot be in the future.'];
        }
        $loadDate = $dateRaw . ' 00:00:00';

        if (! is_numeric($beginEmptyRaw) || (int) $beginEmptyRaw < 0 || (int) $beginEmptyRaw > self::MAX_MILES) {
            return ['ok' => false, 'error' => 'Begin empty miles must be between 0 and ' . self::MAX_MILES . '.'];
        }
        if (! is_numeric($outOfRouteRaw) || (int) $outOfRouteRaw < 0 || (int) $outOfRouteRaw > self::MAX_MILES) {
            return ['ok' => false, 'error' => 'Out-of-route miles must be between 0 and ' . self::MAX_MILES . '.'];
        }
        $beginEmptyMiles = (int) $beginEmptyRaw;
        $outOfRouteMiles = (int) $outOfRouteRaw;
        if ($loadType !== 0) {
            // Round-trip has no separate empty pre-leg — drop a stray value.
            $beginEmptyMiles = 0;
        }
        $outOfRouteInd = $outOfRouteMiles > 0 ? 1 : 0;

        if (! $this->terminals->isKnown($pickup)) {
            return ['ok' => false, 'error' => sprintf('Pick-up "%s" is not a known terminal. Pick from the list.', $pickup)];
        }
        if ($this->cities->findByName($delivery) === null) {
            return ['ok' => false, 'error' => sprintf('Delivery city "%s" is not in the city list. Add it first.', $delivery)];
        }

        $milesBefore = $this->distances->between($pickup, $delivery);
        $emptyMiles  = $this->distances->lookupOrFetch($pickup, $delivery);
        if ($emptyMiles === null) {
            return ['ok' => false, 'error' => sprintf('Could not find a mileage for %s → %s, and Google Maps could not resolve it either.', $pickup, $delivery)];
        }
        $usedGoogleMaps = count($milesBefore) === 0 ? 1 : 0;

        $endEmptyMiles = 0;
        $endEmptyResolved = null;
        if ($loadType === 0 && $endEmpty !== '') {
            if ($endEmpty === $delivery) {
                return ['ok' => false, 'error' => 'End Empty must differ from the delivery city.'];
            }
            $resolved = $this->distances->lookupOrFetch($delivery, $endEmpty);
            if ($resolved === null) {
                return ['ok' => false, 'error' => sprintf('Could not find an empty-leg mileage for %s → %s.', $delivery, $endEmpty)];
            }
            $endEmptyMiles    = $resolved;
            $endEmptyResolved = $endEmpty;
        }

        $variablesBlob = $this->blobBuilder->build($account);
        $payInput = new LoadInputs(
            load_type:          $loadType,
            load_miles:         $emptyMiles,
            empty_miles:        $endEmptyMiles,
            begin_empty_miles:  $beginEmptyMiles,
            is_split:           $isSplit,
            is_weekend:         $isWeekend,
            extra_pay:          (float) $extraRaw,
            dem_minutes:        (int) $demRaw,
            break_minutes:      (int) $brkRaw,
            variables_blob:     $variablesBlob,
            out_of_route_ind:   $outOfRouteInd,
            out_of_route_miles: $outOfRouteMiles,
        );
        $pay = $this->calculator->computeFor($payInput);

        return [
            'ok'   => true,
            'data' => [
                'date'                 => $loadDate,
                'load_type'            => $loadType,
                'pickup_city'          => $pickup,
                'delivery_city'        => $delivery,
                'end_empty_city'       => $endEmptyResolved,
                'end_empty_city_raw'   => $endEmpty,
                'end_empty_miles'      => $endEmptyMiles,
                'empty_miles'          => $emptyMiles,
                'begin_empty_miles'    => $beginEmptyMiles,
                'is_split'             => $isSplit,
                'is_weekend'           => $isWeekend,
                'extra_pay'            => (float) $extraRaw,
                'dem_minutes'          => (int) $demRaw,
                'break_minutes'        => (int) $brkRaw,
                'out_of_route_ind'     => $outOfRouteInd,
                'out_of_route_miles'   => $outOfRouteMiles,
                'used_google_maps'     => $usedGoogleMaps,
                'notes'                => $notes !== '' ? $notes : null,
                'variables'            => $variablesBlob,
                'pay_breakdown'        => $pay,
            ],
        ];
    }
}
