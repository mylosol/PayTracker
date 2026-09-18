<?php

declare(strict_types=1);

/**
 * WYSIWYG resync: pay_rates current + default ← legacy driver-facing pay.
 *
 * Background — the shape of two prior mistakes we're cleaning up in one go:
 *
 *   1. The 2026-06-02 sync migrations captured legacy's *raw* ladder values
 *      (`LHPensacolaPayCurrent` / `PensacolaPayCurrent`) as of 2026-06-02.
 *      What they missed is that legacy multiplies every raw row by
 *      `variablesCurrent.raise` (0.08675) before a driver sees it — so
 *      what the driver was actually paid for a 68-mile long-haul was
 *      $50.94 × 1.08675 = $55.36, not $50.94. The sync pulled $49.4603
 *      (the *raw* row) into modern and modern's calculator faithfully
 *      applied the same 8.675% raise, but every ladder row was still ~7%
 *      below legacy because a later "Peter got a 7% raise" bump had gone
 *      out to legacy without a resync. Net effect: modern drivers have
 *      been paid ~7% less than legacy for the same load since the
 *      migration.
 *
 *   2. A `raise` = 0 promote was applied to `pay_variables` on 2026-09-18
 *      in an attempt to make the admin surface WYSIWYG — but nothing
 *      touched the ladder to compensate, so drivers took a further 8.675%
 *      cut on top of the 7% they were already behind.
 *
 * This migration lands both fixes in one shot by writing
 * `legacy_raw × 1.08675` (rounded to 4dp — the DECIMAL precision the
 * column supports) into modern's default AND current stages. Combined
 * with `raise = 0`, the calculator's output for a load is exactly what
 * legacy would compute for the same load, and Peter can edit any row
 * going forward with a straight "type the driver's pay" mental model.
 *
 * Sources: the legacy paytracking.sql dump the user attached on
 * 2026-09-18, tables `LHPensacolaPayCurrent` (long_haul) and
 * `PensacolaPayCurrent` (round_trip). Every legacy row is included
 * verbatim; the WYSIWYG conversion is done in PHP so a reviewer can
 * see both sides in the diff.
 *
 * Not touched:
 *   - pay_rate_versions: keeps its history, so a load dated *before*
 *     this migration is still repriced at whatever version was active
 *     on that date. Only NEW loads and Recompute-pay runs pick up the
 *     new ladder.
 *   - pay_rates draft: left as-is. If a draft exists that a user still
 *     wants to promote, they can still promote it; if it's stale,
 *     they can Reset draft to current afterwards.
 *   - pay_variables: this migration expects `raise` to already be 0
 *     (the operator promoted that earlier on 2026-09-18). If someone
 *     runs this on an environment where raise is still 0.08675, the
 *     ladder will be 8.675% too high — but that's caught by the
 *     verification print below (before-vs-after totals will move a
 *     recognizable amount).
 */

return static function (PDO $pdo): void {

    // Legacy multiplier — the `raise` variable that legacy applies to
    // every raw row before it hits a load. Written explicitly rather
    // than read from pay_variables so this migration is deterministic
    // and doesn't behave differently depending on the target env's
    // current raise value.
    $LEGACY_RAISE = 0.08675;

    // Legacy long_haul raw ladder (LHPensacolaPayCurrent, 114 rows).
    $legacyLongHaul = [
        [10, 40.2500],   [15, 40.8500],   [20, 43.2900],   [25, 43.2900],
        [30, 44.9700],   [32, 44.9700],   [34, 44.9700],   [36, 44.9700],
        [38, 44.9700],   [40, 44.9700],   [42, 47.2200],   [44, 47.2200],
        [46, 47.2200],   [48, 47.2200],   [50, 49.8600],   [52, 49.8600],
        [54, 49.8600],   [56, 49.8600],   [58, 49.8600],   [60, 50.9400],
        [62, 50.9400],   [64, 50.9400],   [66, 50.9400],   [68, 50.9400],
        [70, 53.8900],   [72, 53.8900],   [74, 54.7600],   [76, 54.7600],
        [78, 56.6800],   [80, 56.6800],   [82, 56.6800],   [84, 58.0000],
        [86, 58.0000],   [88, 61.8300],   [90, 61.8300],   [94, 63.7400],
        [96, 63.7400],   [98, 65.8000],   [100, 65.8000],  [102, 67.8200],
        [104, 68.6200],  [106, 68.6200],  [108, 68.6200],  [110, 69.8400],
        [112, 69.8400],  [114, 72.0100],  [116, 72.0100],  [118, 73.2900],
        [120, 74.9600],  [122, 74.9600],  [124, 74.9600],  [126, 76.8400],
        [128, 76.8400],  [130, 76.8400],  [132, 73.4452],  [134, 73.4452],
        [136, 73.4452],  [138, 75.2531],  [140, 75.2531],  [142, 76.9891],
        [144, 76.9891],  [146, 78.8152],  [148, 78.8152],  [150, 80.5422],
        [152, 80.5422],  [154, 82.1203],  [156, 82.1203],  [158, 83.5525],
        [160, 83.5525],  [162, 86.1235],  [164, 86.1235],  [166, 86.1235],
        [168, 88.0486],  [170, 88.0486],  [172, 88.0486],  [174, 88.0486],
        [176, 88.0486],  [178, 91.5600],  [180, 91.5600],  [182, 91.5600],
        [184, 93.5523],  [186, 93.5523],  [188, 95.0528],  [190, 95.0528],
        [192, 97.8868],  [194, 97.8868],  [196, 97.8868],  [198, 100.5285],
        [200, 100.5285], [202, 100.5285], [204, 100.5285], [206, 151.1903],
        [208, 152.6261], [210, 154.0195], [212, 155.4411], [214, 156.4411],
        [216, 158.2849], [218, 159.6925], [220, 161.1140], [222, 162.5499],
        [224, 163.9435], [226, 177.2653], [228, 178.8293], [230, 180.3932],
        [232, 181.9713], [234, 183.5353], [236, 185.0995], [238, 186.6775],
        [240, 188.2414], [242, 189.8053], [244, 191.3833], [246, 192.9472],
        [248, 194.5114], [250, 196.0895],
    ];

    // Legacy round_trip raw ladder (PensacolaPayCurrent, 114 rows).
    $legacyRoundTrip = [
        [10, 40.9561],   [15, 43.3945],   [20, 45.0863],   [25, 47.3423],
        [30, 49.9807],   [32, 51.0722],   [34, 53.0596],   [36, 54.0197],
        [38, 54.8932],   [40, 56.8218],   [42, 58.7138],   [44, 59.9569],
        [46, 61.9890],   [48, 63.8995],   [50, 65.8281],   [52, 67.9932],
        [54, 68.7935],   [56, 69.9917],   [58, 71.9776],   [60, 73.9429],
        [62, 75.1437],   [64, 77.0359],   [66, 78.1990],   [68, 80.1106],
        [70, 82.0212],   [72, 84.8415],   [74, 85.8054],   [76, 86.3877],
        [78, 86.7880],   [80, 88.7714],   [82, 90.6272],   [84, 92.5194],
        [86, 94.4482],   [88, 96.3219],   [90, 98.2141],   [94, 102.0534],
        [96, 105.9290],  [98, 107.8758],  [100, 108.9672], [102, 109.5676],
        [104, 109.7681], [106, 110.6594], [108, 111.4237], [110, 113.3259],
        [112, 115.2261], [114, 117.0824], [116, 118.9927], [118, 120.8669],
        [120, 122.6861], [122, 124.6143], [124, 126.4887], [126, 128.3808],
        [128, 129.0360], [130, 121.2067], [132, 122.9409], [134, 124.6753],
        [136, 126.3759], [138, 128.1102], [140, 129.8617], [142, 130.3378],
        [144, 130.7460], [146, 132.4464], [148, 134.1637], [150, 135.3030],
        [152, 136.0030], [154, 136.7653], [156, 138.4490], [158, 140.1154],
        [160, 141.8496], [162, 143.5500], [164, 145.2164], [166, 146.9342],
        [168, 148.6005], [170, 150.3007], [172, 151.8824], [174, 153.6844],
        [176, 155.3852], [178, 157.0856], [180, 158.8028], [182, 160.4864],
        [184, 162.1360], [186, 163.8532], [188, 165.5538], [190, 167.2711],
        [192, 168.9714], [194, 170.2468], [196, 172.3555], [198, 174.0220],
        [200, 175.7393], [202, 177.4226], [204, 179.1232], [206, 180.8236],
        [208, 182.5408], [210, 184.2073], [212, 185.9076], [214, 187.5743],
        [216, 189.3087], [218, 190.9923], [220, 192.6923], [222, 194.4097],
        [224, 196.0764], [226, 212.0093], [228, 213.8798], [230, 215.7503],
        [232, 217.6377], [234, 219.5082], [236, 221.3790], [238, 223.2663],
        [240, 225.1367], [242, 227.0071], [244, 228.8944], [246, 230.7649],
        [248, 232.6356], [250, 234.5230],
    ];

    // WYSIWYG conversion: legacy driver pay = raw × (1 + raise). We
    // bake that product into the ladder and expect `raise` to be 0
    // going forward. Rounded to 4dp — the DECIMAL(7,4) precision of
    // `pay_rates.rate`, and enough that (rate × miles) reproduces
    // legacy's stored figures to the cent.
    $toWysiwyg = static function (array $rows) use ($LEGACY_RAISE): array {
        $out = [];
        foreach ($rows as [$miles, $raw]) {
            $out[] = [$miles, round(((float) $raw) * (1.0 + $LEGACY_RAISE), 4)];
        }
        return $out;
    };

    $newLongHaul  = $toWysiwyg($legacyLongHaul);
    $newRoundTrip = $toWysiwyg($legacyRoundTrip);

    // Sample verification points printed to the deploy log so a
    // reviewer can eyeball a couple of well-known cells. Kept out of
    // the write path so a print failure never rolls back the migration.
    $sample = static function (string $label, array $rows, array $spots): void {
        $lookup = [];
        foreach ($rows as [$mi, $rate]) { $lookup[$mi] = $rate; }
        foreach ($spots as $mi) {
            $rate = $lookup[$mi] ?? null;
            if ($rate === null) { continue; }
            echo sprintf("    %s tier %d → %s\n", $label, $mi, number_format((float) $rate, 4));
        }
    };

    $pdo->beginTransaction();
    try {
        // Wipe and re-insert both stages for both trip types. Draft is
        // untouched (see docblock). Terminal is always 'pensacola'.
        $del = $pdo->prepare(
            'DELETE FROM `pay_rates`
             WHERE terminal = ? AND trip_type = ? AND stage IN (?, ?)'
        );
        $ins = $pdo->prepare(
            'INSERT INTO `pay_rates` (terminal, trip_type, stage, miles, rate)
             VALUES (?, ?, ?, ?, ?)'
        );

        $write = static function (string $tripType, array $rows) use ($del, $ins): int {
            $del->execute(['pensacola', $tripType, 'default', 'current']);
            foreach ($rows as [$miles, $rate]) {
                $ins->execute(['pensacola', $tripType, 'default', $miles, $rate]);
                $ins->execute(['pensacola', $tripType, 'current', $miles, $rate]);
            }
            return count($rows);
        };

        $lhCount = $write('long_haul',  $newLongHaul);
        $rtCount = $write('round_trip', $newRoundTrip);

        $pdo->commit();

        echo "  → long_haul refreshed ({$lhCount} tiers × 2 stages, WYSIWYG @ raise=0)\n";
        $sample('long_haul',  $newLongHaul,  [10, 15, 68, 100, 250]);
        echo "  → round_trip refreshed ({$rtCount} tiers × 2 stages, WYSIWYG @ raise=0)\n";
        $sample('round_trip', $newRoundTrip, [10, 44, 100, 200, 250]);
        echo "  NOTE: expects pay_variables.raise = 0. If raise is still 0.08675, the\n"
           . "        calculator will overpay by ~8.675%. Verify via /pay-admin/variables.\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
};
