<?php
/**
 * @var string                                                  $csrfToken
 * @var string                                                  $base
 * @var list<array<string,mixed>>                               $cities
 * @var list<string>                                            $terminals
 * @var array<string,mixed>                                     $driver
 * @var string|null                                             $flash
 * @var array{
 *   pickup:string, delivery:string, load_type:string,
 *   dem:string, break:string, extra:string,
 *   split:string, weekend:string,
 * } $old
 */
layout('layouts/app');
?>
<div class="card">
    <h1>Add a load</h1>

    <p class="muted">Signed in as <strong><?= e((string) ($driver['user'] ?? '')) ?></strong> (driver id <?= (int) ($driver['id'] ?? 0) ?>).</p>

    <?php if ($flash !== null): ?>
        <p class="muted" style="background:#fee2e2;color:#991b1b;border-radius:6px;padding:.6rem .8rem;">
            <?= e($flash) ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/loads" novalidate autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

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

        <p>
            <label for="notes"><strong>Notes (optional)</strong></label><br>
            <textarea id="notes" name="notes" rows="2" maxlength="900"
                      style="width:100%;padding:.5rem;border:1px solid #cbd2da;border-radius:6px;font:inherit;"></textarea>
        </p>

        <p>
            <button type="submit"
                    style="background:var(--accent);color:#fff;border:0;padding:.6rem 1.4rem;border-radius:6px;font:inherit;cursor:pointer;">
                Add load
            </button>
            &nbsp;<a href="<?= e($base) ?>/loads">Cancel</a>
        </p>
    </form>

    <p class="muted" style="margin-top:1.5rem;font-size:13px;">
        Mileage is looked up in the city-distances matrix first; on a miss
        we fall through to Google Maps and cache the result so the next
        load with the same pair stays local.
    </p>
</div>
