<?php
/**
 * @var string                                                  $csrfToken
 * @var string                                                  $base
 * @var list<array<string,mixed>>                               $cities
 * @var list<string>                                            $terminals
 * @var list<array{id:int,name:string,city_id:?int}>            $beTerminals
 * @var array<string,mixed>                                     $driver
 * @var string|null                                             $flash
 * @var array{
 *   frtl:string, pickup:string, delivery:string, load_type:string,
 *   dem:string, break:string, extra:string,
 *   split:string, weekend:string,
 *   notes?:string, end_empty?:string,
 *   date?:string, begin_empty_miles?:string, out_of_route_miles?:string,
 * } $old
 * @var string $mode      'create' (default) or 'edit'
 * @var int    $editFrtl  Only set when mode === 'edit'
 */
layout('layouts/app');

$mode      = $mode      ?? 'create';
$editFrtl  = $editFrtl  ?? 0;
$isEdit    = $mode === 'edit';
$formAction = $isEdit ? $base . '/loads/' . (int) $editFrtl : $base . '/loads';
$today     = date('Y-m-d');
$dateValue = (string) ($old['date'] ?? '');
if ($dateValue === '') {
    $dateValue = $today;
}
// "Begin Empty" is a UI-only checkbox — there's no dedicated column
// on driver_loads, the truth is `begin_empty_miles > 0`. We derive
// the initial checked state from the stored miles so editing a load
// that began empty re-reveals the wrapper with the value still in it.
$beChecked = (int) ($old['begin_empty_miles'] ?? 0) > 0;
$beVisible = $beChecked && ($old['load_type'] ?? '0') !== '1';
?>
<div class="card">
    <h1><?= $isEdit ? 'Edit load #' . (int) $editFrtl : 'Add a load' ?></h1>

    <p class="muted">Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong> (driver id <?= (int) ($driver['id'] ?? 0) ?>).</p>

    <?php if ($flash !== null): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

<?php if (! $isEdit): ?>
    <div id="store-toggle-card" class="card"
         style="background:#f0f9ff;border:1px solid #bae6fd;">
        <label style="display:block;font-size:15px;">
            <input id="store_load_info" type="checkbox" checked
                   style="vertical-align:middle;width:18px;height:18px;margin-right:.4rem;">
            <strong>Store Load Info</strong>
        </label>
        <p class="muted" id="store-toggle-help" style="margin:.5rem 0 0 0;font-size:13px;">
            ON: saves to your account using your FRTL #.
            OFF: keeps the load in this browser only as an
            <em>unconfirmed</em> entry, useful for testing or when
            you don't have the FRTL # yet.
        </p>
        <div id="scratchpad-pitfall" hidden
             style="margin-top:.8rem;background:#fef3c7;color:#854d0e;border:1px solid #fde68a;border-radius:6px;padding:.6rem .8rem;font-size:13px;">
            <strong>Heads up:</strong> with <em>Store Load Info</em> off,
            this load stays <em>unconfirmed</em> &mdash; it lives only
            in your browser:
            <ul style="margin:.4rem 0 .2rem 1.2rem;padding:0;">
                <li>It only shows on <em>today's</em> dashboard — viewing past or future days hides it.</li>
                <li>Clearing this device's browser data, switching browsers, or switching phones will lose it.</li>
                <li>It can't be reconciled against pay until you edit it and add a FRTL # to save it.</li>
            </ul>
            We'll auto-clear it 24 hours after entry. Add the FRTL # whenever your paperwork catches up.
        </div>
    </div>
<?php endif; ?>

    <form method="post" action="<?= e($formAction) ?>" novalidate autocomplete="off"
          data-base-path="<?= e($base) ?>"
          data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="unsaved_id" id="unsaved_id" value="">

        <p id="frtl-block">
            <label for="frtl"><strong>FRTL #</strong></label><br>
            <input id="frtl" name="frtl" type="text" inputmode="numeric" pattern="[0-9]*"
                   value="<?= e((string) $old['frtl']) ?>"
                   <?= $isEdit ? 'readonly' : 'required' ?>
                   style="width:14rem;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;<?= $isEdit ? 'background:#f1f5f9;color:#475569;' : '' ?>">
            <small class="muted">
                <?php if ($isEdit): ?>
                    The FRTL number is locked. To change it, delete this load and re-add.
                <?php else: ?>
                    From your dispatch paperwork. Required to save the load to your account.
                <?php endif; ?>
            </small>
        </p>

        <p>
            <label for="load_date"><strong>Load date</strong></label><br>
            <input id="load_date" name="load_date" type="date" required
                   value="<?= e($dateValue) ?>"
                   max="<?= e($today) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Defaults to today. Set to the actual delivery date if you're entering
                paperwork after the fact &mdash; the dashboard groups by this date,
                not entry time.
            </small>
        </p>

        <div id="begin-empty-wrapper" style="margin:0 0 1rem 0;<?= $beVisible ? '' : 'display:none;' ?>">
            <label for="begin_empty_terminal"><strong>Begin empty from</strong></label><br>
            <select id="begin_empty_terminal"
                    data-mode="<?= $isEdit ? 'edit' : 'create' ?>"
                    style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:18rem;max-width:100%;">
                <?php if ($isEdit): ?>
                    <option value="keep" selected>— Keep current miles —</option>
                <?php else: ?>
                    <option value="" selected>— I started at the terminal (0 miles) —</option>
                <?php endif; ?>
                <?php foreach ($beTerminals as $t): ?>
                    <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
                <option value="other">Other / I'll type my own miles…</option>
            </select>
            <small class="muted" style="display:block;margin-top:.25rem;">
                Pick the terminal you started empty from. We'll fill in
                the miles to your pickup automatically.
            </small>
            <p id="begin-empty-status"
               style="margin:.4rem 0 .2rem 0;font-size:13px;display:none;"></p>
            <label for="begin_empty_miles"
                   style="display:block;margin-top:.4rem;font-weight:600;">Begin empty miles</label>
            <input id="begin_empty_miles" name="begin_empty_miles" type="number" min="0" max="9999" step="1"
                   value="<?= e((string) ($old['begin_empty_miles'] ?? '0')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
            <small class="muted">
                Auto-filled by the terminal pick above. Pick
                <em>Other</em> to type the miles yourself.
            </small>
        </div>

        <p>
            <label for="pickup_city"><strong>Pick-up terminal</strong></label><br>
            <select id="pickup_city" name="pickup_city" required
                    style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
                <option value="" disabled <?= $old['pickup'] === '' ? 'selected' : '' ?>>Choose a terminal&hellip;</option>
                <?php foreach ($terminals as $t): ?>
                    <option value="<?= e($t) ?>" <?= $old['pickup'] === $t ? 'selected' : '' ?>>
                        <?= e($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Drivers pick up loads at one of the dispatch terminals; the full city list is for delivery only.</small>
        </p>

        <p>
            <label for="delivery_city"><strong>Delivery city</strong></label><br>
            <input list="city-options" id="delivery_city" name="delivery_city" type="text" required
                   value="<?= e((string) $old['delivery']) ?>"
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
        </p>

        <datalist id="city-options">
            <?php foreach ($cities as $c): ?>
                <option value="<?= e((string) $c['city']) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <fieldset style="border:1px solid #cbd2da;border-radius:6px;padding:.6rem 1rem;margin:0 0 1rem 0;">
            <legend><strong>Load type</strong></legend>
            <label style="margin-right:1.2rem;">
                <input type="radio" name="load_type" value="0" <?= $old['load_type'] !== '1' ? 'checked' : '' ?>>
                Loaded one-way
            </label>
            <label>
                <input type="radio" name="load_type" value="1" <?= $old['load_type'] === '1' ? 'checked' : '' ?>>
                Round-trip
            </label>
        </fieldset>

        <p id="end-empty-wrapper" style="<?= $old['load_type'] === '1' ? 'display:none;' : '' ?>">
            <label for="end_empty_city"><strong>End Empty location</strong></label><br>
            <input list="city-options" id="end_empty_city" name="end_empty_city" type="text"
                   value="<?= e((string) ($old['end_empty'] ?? '')) ?>"
                   style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;">
            <small class="muted">
                Where you ended after the delivery (typically the terminal you returned to).
                Empty leg = delivery &rarr; here. Leave blank if you didn't go anywhere empty.
            </small>
        </p>

        <p>
            <label>
                <input type="checkbox" name="is_split" value="1" <?= $old['split'] === '1' ? 'checked' : '' ?>>
                Split load
            </label>
            &nbsp;&nbsp;
            <label>
                <input type="checkbox" name="is_weekend" value="1" <?= $old['weekend'] === '1' ? 'checked' : '' ?>>
                Weekend
            </label>
            &nbsp;&nbsp;
            <label id="begin-empty-toggle-label">
                <input type="checkbox" id="begin_empty_checkbox" <?= $beChecked ? 'checked' : '' ?>>
                Begin Empty
            </label>
        </p>

        <p>
            <label for="dem_minutes"><strong>Demurrage minutes</strong></label><br>
            <input id="dem_minutes" name="dem_minutes" type="number" min="0" max="1440" step="1"
                   value="<?= e((string) $old['dem']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
            <small class="muted">0 if none.</small>
        </p>

        <p>
            <label for="break_minutes"><strong>Breakdown minutes</strong></label><br>
            <input id="break_minutes" name="break_minutes" type="number" min="0" max="1440" step="1"
                   value="<?= e((string) $old['break']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
            <small class="muted">0 if none.</small>
        </p>

        <p>
            <label for="extra_pay"><strong>Extra pay ($)</strong></label><br>
            <input id="extra_pay" name="extra_pay" type="number" min="0" max="999.99" step="0.01"
                   value="<?= e((string) $old['extra']) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
        </p>

        <p>
            <label for="out_of_route_miles"><strong>Out-of-route miles</strong></label><br>
            <input id="out_of_route_miles" name="out_of_route_miles" type="number" min="0" max="9999" step="1"
                   value="<?= e((string) ($old['out_of_route_miles'] ?? '0')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
            <small class="muted">
                Total <em>actual</em> loaded miles when a detour added significant distance
                (construction, road closure, etc.). Only used if it exceeds the
                map distance by more than 3 miles; otherwise the map distance pays.
            </small>
        </p>

        <p>
            <label for="notes"><strong>Notes (optional)</strong></label><br>
            <textarea id="notes" name="notes" rows="2" maxlength="900"
                      style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;"><?= e((string) ($old['notes'] ?? '')) ?></textarea>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                <?= $isEdit ? 'Save changes' : 'Add load' ?>
            </button>
            &nbsp;<a href="<?= e($base) ?>/<?= $isEdit ? 'dashboard' : 'loads' ?>">Cancel</a>
        </p>
    </form>

    <script>
        // Visibility rules for the two empty-leg sections.
        //
        // End Empty:    shown only on one-way (round-trip has no
        //               distinct end-empty leg). Cleared when hidden.
        // Begin Empty:  shown only when (a) one-way AND (b) the
        //               "Begin Empty" checkbox is ticked. Cleared
        //               (miles → 0) when either condition flips off,
        //               so a stale value can't sneak back if the
        //               driver hides → re-shows the section.
        //
        // The Begin Empty checkbox is UI-only; nothing on the server
        // cares whether it was checked, only what miles got submitted.
        // We could leave begin_empty_miles intact when hiding, but the
        // legacy behavior was "round-trip implies 0" and we want
        // ticking off the checkbox to feel like the same intent
        // ("I didn't begin empty on this load").
        (function () {
            const endWrap   = document.getElementById('end-empty-wrapper');
            const endInput  = document.getElementById('end_empty_city');
            const beWrap    = document.getElementById('begin-empty-wrapper');
            const beMiles   = document.getElementById('begin_empty_miles');
            const beToggle  = document.getElementById('begin_empty_checkbox');
            const radios    = document.querySelectorAll('input[name="load_type"]');

            const refresh = () => {
                const sel      = document.querySelector('input[name="load_type"]:checked');
                const isOneWay = sel && sel.value === '0';

                if (endWrap && endInput) {
                    endWrap.style.display = isOneWay ? '' : 'none';
                    if (!isOneWay) endInput.value = '';
                }
                if (beWrap) {
                    const beOn = isOneWay && beToggle && beToggle.checked;
                    beWrap.style.display = beOn ? '' : 'none';
                    if (!beOn && beMiles) beMiles.value = '0';
                    // When round-trip is picked, also force-untick the
                    // checkbox — flipping back to one-way should NOT
                    // surprise the driver with the section reappearing
                    // unless they deliberately tick it again.
                    if (!isOneWay && beToggle && beToggle.checked) {
                        beToggle.checked = false;
                    }
                }
            };

            radios.forEach(r => r.addEventListener('change', refresh));
            if (beToggle) beToggle.addEventListener('change', refresh);
            refresh();
        })();
    </script>

    <script>
        // -------------------------------------------------------------------
        // Begin Empty terminal picker.
        //
        // Replaces the legacy "drivers eyeball the miles" UX. When the
        // driver picks a terminal from the dropdown, we POST to
        // /loads/preview-be-miles and the server resolves miles via the
        // city_distances cache (with a Google Maps fill-fallback). The
        // resulting integer flows into #begin_empty_miles and the input
        // is disabled so it can't be accidentally edited.
        //
        // Selecting "Other" re-enables the input for manual entry (the
        // legacy app couldn't handle non-terminal starts; ours can).
        // Selecting the empty placeholder zeros the miles — "started at
        // the terminal" is the most common case and one click should
        // express it.
        //
        // Failure modes are friendly: if the lookup misses both the
        // cache AND Google Maps (no API key on dev, transient outage),
        // we show a short inline message and unlock the input so the
        // driver can fill miles in by hand. The form is never blocked.
        // -------------------------------------------------------------------
        (function () {
            const picker = document.getElementById('begin_empty_terminal');
            const miles  = document.getElementById('begin_empty_miles');
            const status = document.getElementById('begin-empty-status');
            const pickup = document.getElementById('pickup_city');
            const csrf   = document.querySelector('input[name="_csrf"]');
            const form   = document.querySelector('form[data-base-path]');
            if (!picker || !miles || !status || !pickup || !csrf || !form) return;
            const basePath = form.dataset.basePath || '';

            const setStatus = (msg, level) => {
                if (!msg) {
                    status.style.display = 'none';
                    status.textContent = '';
                    return;
                }
                status.textContent = msg;
                status.style.display = '';
                if (level === 'error') {
                    status.style.color = '#991b1b';
                } else if (level === 'info') {
                    status.style.color = '#5a6470';
                } else {
                    status.style.color = '#166534';
                }
            };
            const lockMiles = (locked) => {
                miles.readOnly = locked;
                miles.style.background = locked ? '#f1f5f9' : '';
                miles.style.color      = locked ? '#475569' : '';
            };

            picker.addEventListener('change', async () => {
                const val = picker.value;

                if (val === 'keep') {
                    // Edit mode placeholder. Treat as a no-op so an
                    // existing saved miles value stays intact when
                    // the picker is flipped back to the default.
                    lockMiles(false);
                    setStatus(null);
                    return;
                }
                if (val === '') {
                    // "I started at the terminal" — clean zero.
                    miles.value = '0';
                    lockMiles(true);
                    setStatus(null);
                    return;
                }
                if (val === 'other') {
                    // Hand the input back to the driver. Keep the
                    // existing value as the seed so flipping back and
                    // forth doesn't drop a hand-typed number.
                    lockMiles(false);
                    setStatus('Type the miles yourself.', 'info');
                    miles.focus();
                    return;
                }

                if (!pickup.value) {
                    setStatus('Pick your delivery pick-up terminal first, then choose a Begin Empty terminal.', 'error');
                    return;
                }
                setStatus('Looking up miles…', 'info');
                const body = new URLSearchParams();
                body.set('_csrf', csrf.value);
                body.set('begin_empty_terminal_id', val);
                body.set('pickup_city', pickup.value);
                try {
                    const res = await fetch(basePath + '/loads/preview-be-miles', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        credentials: 'same-origin',
                        body,
                    });
                    const data = await res.json();
                    if (!data || !data.ok) {
                        setStatus((data && data.error) || 'Lookup failed. Type the miles yourself.', 'error');
                        lockMiles(false);
                        return;
                    }
                    miles.value = String(data.miles);
                    lockMiles(true);
                    const tag = data.source === 'maps'
                        ? '(via Google Maps — saved to the distance cache)'
                        : '(from the distance cache)';
                    setStatus(`${data.miles} mi from ${data.terminal} → ${pickup.value} ${tag}.`, 'ok');
                } catch (err) {
                    setStatus('Lookup failed — network issue. Type the miles yourself.', 'error');
                    lockMiles(false);
                }
            });

            // Re-resolve when the pickup-city changes IF a terminal is
            // currently selected — otherwise the displayed miles drift
            // out of sync with the route shown above.
            pickup.addEventListener('change', () => {
                if (picker.value !== '' && picker.value !== 'other') {
                    picker.dispatchEvent(new Event('change'));
                }
            });

            // Initial state: input is unlocked on first paint (the
            // picker defaults to "I started at the terminal" only AFTER
            // the user opens it). Don't fire a lookup automatically —
            // the legacy default is 0 miles.
            lockMiles(false);
        })();
    </script>

    <script>
        // -------------------------------------------------------------------
        // Scratchpad "Store Load Info" checkbox + form interceptor.
        //
        // When the checkbox is ON, the form submits to /loads as usual.
        // When OFF, the form POSTs to /loads/preview which returns a JSON
        // computed-pay row; the row is stashed in localStorage and the
        // driver lands on the dashboard where a hydration script injects
        // the unsaved rows into today's Loads table.
        //
        // The pay math NEVER runs client-side — the server is the single
        // source of truth. The client only stores precomputed np/op
        // figures and SUMS them into the dashboard totals.
        //
        // Rolling 24-hour expiry: stored on each entry as `created_at`
        // (epoch ms). Purge on every read.
        // -------------------------------------------------------------------
        (function () {
            const form = document.querySelector('form[data-base-path]');
            if (!form) return;
            const mode      = form.dataset.mode;          // 'create' or 'edit'
            const basePath  = form.dataset.basePath || '';
            const toggle    = document.getElementById('store_load_info');
            const frtlBlock = document.getElementById('frtl-block');
            const frtlInput = document.getElementById('frtl');
            const pitfall   = document.getElementById('scratchpad-pitfall');
            const unsavedId = document.getElementById('unsaved_id');

            const STORE_PREF_KEY = 'paytracker.storeLoads';   // bool
            const ENTRIES_KEY    = 'paytracker.unsavedLoads'; // array
            const TTL_MS         = 24 * 60 * 60 * 1000;

            function readEntries() {
                let raw;
                try { raw = localStorage.getItem(ENTRIES_KEY); }
                catch (e) { return []; }
                if (!raw) return [];
                let arr;
                try { arr = JSON.parse(raw); }
                catch (e) { return []; }
                if (!Array.isArray(arr)) return [];
                const now = Date.now();
                const live = arr.filter(e => e && typeof e === 'object'
                    && typeof e.created_at === 'number'
                    && (now - e.created_at) < TTL_MS);
                if (live.length !== arr.length) writeEntries(live);
                return live;
            }
            function writeEntries(arr) {
                try { localStorage.setItem(ENTRIES_KEY, JSON.stringify(arr)); }
                catch (e) { /* quota or disabled — fail silent */ }
            }
            function upsertEntry(entry) {
                const all = readEntries();
                const idx = all.findIndex(e => e.local_id === entry.local_id);
                if (idx >= 0) all[idx] = entry;
                else          all.push(entry);
                writeEntries(all);
            }
            function findEntry(localId) {
                return readEntries().find(e => e.local_id === localId) || null;
            }
            function deleteEntry(localId) {
                writeEntries(readEntries().filter(e => e.local_id !== localId));
            }
            function newLocalId() {
                return 'u_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
            }

            // Pending-clear handoff: when a driver edited a scratchpad
            // entry, supplied a FRTL, and the form POSTed successfully
            // to /loads, the redirect lands them on the dashboard. The
            // hidden field below tells the dashboard which localId to
            // drop from the scratchpad. (We use sessionStorage rather
            // than a query string so the local_id never appears in
            // server logs.)
            function markPendingClear(localId) {
                try { sessionStorage.setItem('paytracker.consumeLocalId', localId); }
                catch (e) { /* fail silent */ }
            }

            // --- Checkbox state ---------------------------------------------
            function readPref() {
                let raw;
                try { raw = localStorage.getItem(STORE_PREF_KEY); }
                catch (e) { return true; }
                if (raw === null) return true; // default ON
                return raw === '1';
            }
            function writePref(on) {
                try { localStorage.setItem(STORE_PREF_KEY, on ? '1' : '0'); }
                catch (e) { /* fail silent */ }
            }
            function applyToggleState() {
                if (!toggle) return;
                const on = toggle.checked;
                if (frtlBlock) frtlBlock.style.display = on ? '' : 'none';
                if (pitfall)   pitfall.hidden          = on;
                if (frtlInput) {
                    // Make required only when storing (so an OFF submit
                    // doesn't hit the HTML5 required guard).
                    if (on) frtlInput.setAttribute('required', '');
                    else    frtlInput.removeAttribute('required');
                }
            }
            if (toggle) {
                toggle.checked = readPref();
                applyToggleState();
                toggle.addEventListener('change', () => {
                    writePref(toggle.checked);
                    applyToggleState();
                });
            }

            // --- Edit-of-unsaved hydration ---------------------------------
            // When the URL has ?unsaved=<localId>, this is an edit of a
            // scratchpad entry. Prefill the form, force the checkbox OFF
            // (the row is currently unsaved), and remember the localId
            // so submit knows which entry to update or consume.
            const urlParams = new URLSearchParams(window.location.search);
            const hydrateId = urlParams.get('unsaved');
            let editingLocalId = null;
            if (hydrateId && mode === 'create') {
                const entry = findEntry(hydrateId);
                if (entry && entry.computed) {
                    editingLocalId = entry.local_id;
                    if (unsavedId) unsavedId.value = entry.local_id;
                    // The entry was saved with checkbox OFF — keep it that
                    // way on first render. Adding a FRTL # will auto-flip.
                    if (toggle) { toggle.checked = false; applyToggleState(); }
                    // Prefill fields.
                    const c = entry.computed;
                    const setVal = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined && v !== null) el.value = v; };
                    setVal('frtl', '');
                    setVal('load_date', c.date);
                    setVal('pickup_city', c.pickup_city);
                    setVal('delivery_city', c.delivery_city);
                    setVal('end_empty_city', c.end_empty_city || '');
                    setVal('dem_minutes', String(c.dem_minutes ?? 0));
                    setVal('break_minutes', String(c.break_minutes ?? 0));
                    setVal('extra_pay', String(c.extra_pay ?? 0));
                    setVal('begin_empty_miles', String(c.begin_empty_miles ?? 0));
                    // Re-tick the Begin Empty checkbox when the stored
                    // miles are non-zero so the wrapper re-reveals with
                    // the value still in it. Fire change so the unified
                    // visibility refresher updates the wrapper too.
                    const beCb = document.getElementById('begin_empty_checkbox');
                    if (beCb) {
                        beCb.checked = Number(c.begin_empty_miles ?? 0) > 0;
                        beCb.dispatchEvent(new Event('change'));
                    }
                    setVal('out_of_route_miles', String(c.out_of_route_miles ?? 0));
                    setVal('notes', c.notes || '');
                    const radio = document.querySelector(
                        `input[name="load_type"][value="${c.load_type === 1 ? '1' : '0'}"]`);
                    if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
                    const split = document.querySelector('input[name="is_split"]');
                    if (split) split.checked = c.is_split === 1;
                    const wk = document.querySelector('input[name="is_weekend"]');
                    if (wk) wk.checked = c.is_weekend === 1;
                    // Update the page heading so the driver knows they're editing.
                    const h1 = document.querySelector('h1');
                    if (h1) h1.textContent = 'Edit unconfirmed load';
                }
            }

            // --- Auto-flip checkbox ON when FRTL gets a value --------------
            if (frtlInput && toggle && !toggle.checked) {
                frtlInput.addEventListener('input', () => {
                    if (frtlInput.value.trim() !== '' && !toggle.checked) {
                        toggle.checked = true;
                        writePref(true);
                        applyToggleState();
                    }
                });
            }

            // --- Submit interceptor ----------------------------------------
            // Edit mode (server-backed): leave alone.
            if (mode !== 'create') return;

            form.addEventListener('submit', async (ev) => {
                // Path A: checkbox ON — saving to DB. If we're converting
                // a scratchpad entry, mark it for the dashboard to clear.
                if (toggle && toggle.checked) {
                    if (editingLocalId) markPendingClear(editingLocalId);
                    return; // normal POST to /loads
                }
                // Path B: checkbox OFF — preview + localStorage.
                ev.preventDefault();
                const data = new FormData(form);
                // FRTL field is hidden + not required; drop any stale value
                // so it can't taint a future save.
                data.delete('frtl');
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                let resp, json;
                try {
                    resp = await fetch(basePath + '/loads/preview', {
                        method: 'POST',
                        body:   data,
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    json = await resp.json();
                } catch (e) {
                    if (submitBtn) submitBtn.disabled = false;
                    alert('Could not reach the server to preview the load. Check your connection and try again.');
                    return;
                }
                if (!json || json.ok !== true) {
                    if (submitBtn) submitBtn.disabled = false;
                    alert((json && json.error) || 'The server rejected the load. Please review your inputs.');
                    return;
                }
                const entry = {
                    local_id:   editingLocalId || newLocalId(),
                    created_at: Date.now(),
                    computed:   json.computed,
                };
                upsertEntry(entry);
                window.location.href = basePath + '/dashboard';
            });
        })();
    </script>

    <p class="muted" style="margin-top:1.5rem;font-size:13px;">
        Mileage is looked up in the city-distances matrix first; on a miss
        we fall through to Google Maps and cache the result so the next
        load with the same pair stays local.
    </p>
</div>
