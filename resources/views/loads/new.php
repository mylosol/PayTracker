<?php
/**
 * @var string                                                  $csrfToken
 * @var string                                                  $base
 * @var list<array<string,mixed>>                               $cities
 * @var list<string>                                            $terminals
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
            OFF: keeps the load in this browser only as a scratchpad,
            useful for testing or when you don't have the FRTL # yet.
        </p>
        <div id="scratchpad-pitfall" hidden
             style="margin-top:.8rem;background:#fef3c7;color:#854d0e;border:1px solid #fde68a;border-radius:6px;padding:.6rem .8rem;font-size:13px;">
            <strong>Heads up:</strong> with <em>Store Load Info</em> off,
            this load lives only in your browser:
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

        <p id="begin-empty-wrapper" style="<?= $old['load_type'] === '1' ? 'display:none;' : '' ?>">
            <label for="begin_empty_miles"><strong>Begin empty miles</strong></label><br>
            <input id="begin_empty_miles" name="begin_empty_miles" type="number" min="0" max="9999" step="1"
                   value="<?= e((string) ($old['begin_empty_miles'] ?? '0')) ?>"
                   style="padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;width:8rem;">
            <small class="muted">
                Miles driven empty BEFORE pick-up (e.g. home &rarr; terminal).
                Paid at the empty-miles rate. 0 if you started at the terminal.
                Hidden on Round-trip &mdash; round-trips don't begin empty.
            </small>
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
        // Hide End Empty AND Begin Empty when the user picks Round-trip
        // — round-trip doesn't have separate empty legs in the legacy
        // formula (the return leg is implicit in the round-trip rate
        // table) and the controller silently drops both values for
        // round-trip. Showing them just confuses the driver. We also
        // clear the values on hide so a stale one-way value doesn't
        // sneak back if they toggle a third time and forget.
        (function () {
            const pairs = [
                { wrapper: 'end-empty-wrapper',   input: 'end_empty_city',    blank: '' },
                { wrapper: 'begin-empty-wrapper', input: 'begin_empty_miles', blank: '0' },
            ].map(p => ({
                wrapper: document.getElementById(p.wrapper),
                input:   document.getElementById(p.input),
                blank:   p.blank,
            })).filter(p => p.wrapper && p.input);
            if (pairs.length === 0) return;
            const radios = document.querySelectorAll('input[name="load_type"]');
            const refresh = () => {
                const sel = document.querySelector('input[name="load_type"]:checked');
                const isOneWay = sel && sel.value === '0';
                pairs.forEach(p => {
                    p.wrapper.style.display = isOneWay ? '' : 'none';
                    if (!isOneWay) p.input.value = p.blank;
                });
            };
            radios.forEach(r => r.addEventListener('change', refresh));
            refresh();
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
                    if (h1) h1.textContent = 'Edit unsaved load';
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
