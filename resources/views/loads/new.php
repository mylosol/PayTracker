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
<div class="max-w-2xl mx-auto">
<div class="card">
    <h1 class="m-0"><?= $isEdit ? 'Edit load #' . (int) $editFrtl : 'Add a load' ?></h1>
    <p class="text-brand-muted mt-2">
        Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong>
        (driver id <?= (int) ($driver['id'] ?? 0) ?>).
    </p>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-err" role="alert"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (! $isEdit): ?>
    <div id="store-toggle-card" class="card bg-sky-50 border-sky-200">
        <label class="flex items-start gap-3 cursor-pointer min-h-[44px]">
            <input id="store_load_info" type="checkbox" checked class="field-checkbox mt-1">
            <span>
                <strong class="text-base">Store Load Info</strong>
                <span class="block text-sm text-brand-muted mt-1" id="store-toggle-help">
                    ON: saves to your account using your FRTL #.
                    OFF: keeps the load in this browser only as an
                    <em>unconfirmed</em> entry, useful for testing or when
                    you don't have the FRTL # yet.
                </span>
            </span>
        </label>
        <div id="scratchpad-pitfall" hidden
             class="mt-3 rounded-lg px-3.5 py-3 bg-amber-50 text-amber-900 border border-amber-200 text-sm">
            <strong>Heads up:</strong> with <em>Store Load Info</em> off,
            this load stays <em>unconfirmed</em> — it lives only
            in your browser:
            <ul class="list-disc list-inside mt-2 space-y-1">
                <li>It only shows on <em>today's</em> dashboard — viewing past or future days hides it.</li>
                <li>Clearing this device's browser data, switching browsers, or switching phones will lose it.</li>
                <li>It can't be reconciled against pay until you edit it and add a FRTL # to save it.</li>
            </ul>
            <p class="mt-2">We'll auto-clear it 24 hours after entry. Add the FRTL # whenever your paperwork catches up.</p>
        </div>
    </div>
<?php endif; ?>

<div class="bg-white border border-brand-line rounded-xl2 shadow-card mb-4 overflow-hidden">
    <form method="post" action="<?= e($formAction) ?>" novalidate autocomplete="off"
          data-base-path="<?= e($base) ?>"
          data-mode="<?= $isEdit ? 'edit' : 'create' ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="unsaved_id" id="unsaved_id" value="">

        <!-- Trip basics -->
        <div class="form-section">
            <div class="form-section-title">Trip basics</div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div id="frtl-block">
                    <label for="frtl" class="field-label">FRTL #</label>
                    <input id="frtl" name="frtl" type="text" inputmode="numeric" pattern="[0-9]*"
                           value="<?= e((string) $old['frtl']) ?>"
                           <?= $isEdit ? 'readonly' : 'required' ?>
                           class="field <?= $isEdit ? 'bg-slate-100 text-slate-500' : '' ?>">
                    <span class="field-hint">
                        <?php if ($isEdit): ?>
                            Locked. Delete &amp; re-add to change.
                        <?php else: ?>
                            From your dispatch paperwork.
                        <?php endif; ?>
                    </span>
                </div>
                <div>
                    <label for="load_date" class="field-label">Load date</label>
                    <input id="load_date" name="load_date" type="date" required
                           value="<?= e($dateValue) ?>" max="<?= e($today) ?>"
                           class="field">
                    <span class="field-hint">Defaults to today.</span>
                </div>
            </div>

            <div id="begin-empty-wrapper"
                 class="rounded-lg bg-slate-50 border border-brand-line px-4 py-4 mb-4"
                 style="<?= $beVisible ? '' : 'display:none;' ?>">
                <label for="begin_empty_terminal" class="field-label">Begin empty from</label>
                <select id="begin_empty_terminal"
                        data-mode="<?= $isEdit ? 'edit' : 'create' ?>"
                        class="field-select max-w-md">
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
                <span class="field-hint">
                    Pick the terminal you started empty from. We'll fill in
                    the miles to your pickup automatically.
                </span>
                <p id="begin-empty-status" class="text-sm mt-2 mb-0" style="display:none;"></p>
                <label for="begin_empty_miles" class="field-label mt-3">Begin empty miles</label>
                <input id="begin_empty_miles" name="begin_empty_miles" type="number" min="0" max="9999" step="1"
                       value="<?= e((string) ($old['begin_empty_miles'] ?? '0')) ?>"
                       class="field max-w-[8rem]">
                <span class="field-hint">
                    Auto-filled by the terminal pick above. Pick
                    <em>Other</em> to type the miles yourself.
                </span>
            </div>

            <div class="mb-4">
                <label for="pickup_city" class="field-label">Pick-up terminal</label>
                <select id="pickup_city" name="pickup_city" required class="field-select">
                    <option value="" disabled <?= $old['pickup'] === '' ? 'selected' : '' ?>>Choose a terminal…</option>
                    <?php foreach ($terminals as $t): ?>
                        <option value="<?= e($t) ?>" <?= $old['pickup'] === $t ? 'selected' : '' ?>>
                            <?= e($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">
                    Drivers pick up loads at one of the dispatch terminals.
                </span>
            </div>

            <div>
                <label for="delivery_city" class="field-label">Delivery city</label>
                <input list="city-options" id="delivery_city" name="delivery_city" type="text" required
                       value="<?= e((string) $old['delivery']) ?>" class="field">
                <datalist id="city-options">
                    <?php foreach ($cities as $c): ?>
                        <option value="<?= e((string) $c['city']) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
        </div>

        <!-- Load type & flags -->
        <div class="form-section">
            <div class="form-section-title">Load type &amp; flags</div>

            <div class="flex flex-wrap gap-x-6 gap-y-2 mb-4">
                <label class="inline-flex items-center gap-2 min-h-[44px]">
                    <input type="radio" name="load_type" value="0" class="field-radio"
                           <?= $old['load_type'] !== '1' ? 'checked' : '' ?>>
                    <span>Loaded one-way</span>
                </label>
                <label class="inline-flex items-center gap-2 min-h-[44px]">
                    <input type="radio" name="load_type" value="1" class="field-radio"
                           <?= $old['load_type'] === '1' ? 'checked' : '' ?>>
                    <span>Round-trip</span>
                </label>
            </div>

            <div id="end-empty-wrapper" class="mb-4" style="<?= $old['load_type'] === '1' ? 'display:none;' : '' ?>">
                <label for="end_empty_city" class="field-label">End Empty terminal</label>
                <select id="end_empty_city" name="end_empty_city" class="field-select">
                    <option value="" <?= ($old['end_empty'] ?? '') === '' ? 'selected' : '' ?>>— I didn't go anywhere empty —</option>
                    <?php foreach ($terminals as $t): ?>
                        <option value="<?= e($t) ?>" <?= ($old['end_empty'] ?? '') === $t ? 'selected' : '' ?>>
                            <?= e($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">
                    Pick the terminal you returned to after the delivery.
                    Empty leg = delivery → here.
                </span>
            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <label class="inline-flex items-center gap-2 min-h-[44px]">
                    <input type="checkbox" name="is_split" value="1" class="field-checkbox"
                           <?= $old['split'] === '1' ? 'checked' : '' ?>>
                    <span>Split load</span>
                </label>
                <label class="inline-flex items-center gap-2 min-h-[44px]">
                    <input type="checkbox" name="is_weekend" value="1" class="field-checkbox"
                           <?= $old['weekend'] === '1' ? 'checked' : '' ?>>
                    <span>Weekend</span>
                </label>
                <label id="begin-empty-toggle-label" class="inline-flex items-center gap-2 min-h-[44px]">
                    <input type="checkbox" id="begin_empty_checkbox" class="field-checkbox"
                           <?= $beChecked ? 'checked' : '' ?>>
                    <span>Begin Empty</span>
                </label>
            </div>
        </div>

        <!-- Adjustments -->
        <div class="form-section">
            <div class="form-section-title">Adjustments <span class="font-normal normal-case tracking-normal">(0 if none)</span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="dem_minutes" class="field-label">Demurrage min</label>
                    <input id="dem_minutes" name="dem_minutes" type="number" min="0" max="1440" step="1"
                           value="<?= e((string) $old['dem']) ?>"
                           class="field">
                </div>
                <div>
                    <label for="break_minutes" class="field-label">Breakdown min</label>
                    <input id="break_minutes" name="break_minutes" type="number" min="0" max="1440" step="1"
                           value="<?= e((string) $old['break']) ?>"
                           class="field">
                </div>
                <div>
                    <label for="extra_pay" class="field-label">Extra pay ($)</label>
                    <input id="extra_pay" name="extra_pay" type="number" min="0" max="999.99" step="0.01"
                           value="<?= e((string) $old['extra']) ?>"
                           class="field">
                </div>
                <div>
                    <label for="out_of_route_miles" class="field-label">OOR miles</label>
                    <input id="out_of_route_miles" name="out_of_route_miles" type="number" min="0" max="9999" step="1"
                           value="<?= e((string) ($old['out_of_route_miles'] ?? '0')) ?>"
                           class="field">
                </div>
            </div>
        </div>

        <!-- Notes + submit -->
        <div class="form-section">
            <div class="form-section-title">Notes <span class="font-normal normal-case tracking-normal">(optional)</span></div>
            <textarea id="notes" name="notes" rows="2" maxlength="900"
                      class="field mb-4"><?= e((string) ($old['notes'] ?? '')) ?></textarea>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <button type="submit" class="btn-primary">
                    <?= $isEdit ? 'Save changes' : 'Add load' ?>
                </button>
                <a href="<?= e($base) ?>/dashboard" class="text-sm">Cancel</a>
            </div>
        </div>
    </form>
</div>
</div>

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
                    // The Csrf class rotates the token on every
                    // successful verify(), so a second picker change
                    // would otherwise POST with a stale value. Every
                    // response (ok or not) carries the next live token
                    // — swap it into the form's hidden field so the
                    // next call goes through.
                    if (data && typeof data.next_csrf === 'string' && data.next_csrf !== '') {
                        csrf.value = data.next_csrf;
                    }
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
                // Token-rotate: keep the form's hidden _csrf in sync
                // with the server's freshly-issued one so a second
                // submission (or follow-up BE picker change) doesn't
                // hit "session expired".
                if (json && typeof json.next_csrf === 'string' && json.next_csrf !== '') {
                    const csrfEl = form.querySelector('input[name="_csrf"]');
                    if (csrfEl) csrfEl.value = json.next_csrf;
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

    <div class="max-w-2xl mx-auto">
        <div class="card">
            <p class="text-sm text-brand-muted m-0">
                Mileage is looked up in the city-distances matrix first; on a miss
                we fall through to Google Maps and cache the result so the next
                load with the same pair stays local.
            </p>
        </div>
    </div>
