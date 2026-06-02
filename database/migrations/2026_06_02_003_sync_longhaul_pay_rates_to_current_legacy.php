<?php

declare(strict_types=1);

/*
 * Sister to 2026_06_02_002 — does the same thing for the long_haul
 * (one-way) rate table.
 *
 * Source: live legacy `LHPensacolaPayCurrent`, exported 2026-06-02.
 *
 * Scope:
 *   - pay_rates (terminal='pensacola', trip_type='long_haul')
 *       stages 'default' AND 'current' both replaced.
 *
 * NOT touched:
 *   - round_trip rows (synced separately by 2026_06_02_002)
 *   - panama-terminal rows (vestigial since the collapse)
 *   - any 'draft' stage (would clobber in-flight admin edits)
 *
 * After this lands, one-way loads will look up rates against the
 * current legacy values. Drivers should hit Refresh-my-pay to
 * backfill old one-way loads against the new rates.
 */

return static function (PDO $pdo): void {

    $payRates = [
        [10, '38.1809'],   [15, '38.1809'],   [20, '40.4542'],   [25, '40.4542'],
        [30, '42.0316'],   [32, '42.0316'],   [34, '42.0316'],   [36, '42.0316'],
        [38, '42.0316'],   [40, '42.0316'],   [42, '44.1346'],   [44, '44.1346'],
        [46, '44.1346'],   [48, '44.1346'],   [50, '46.5941'],   [52, '46.5941'],
        [54, '46.5941'],   [56, '46.5941'],   [58, '46.5941'],   [60, '47.6118'],
        [62, '47.6118'],   [64, '47.6118'],   [66, '49.4603'],   [68, '49.4603'],
        [70, '50.3595'],   [72, '50.3595'],   [74, '51.1738'],   [76, '51.1738'],
        [78, '52.9718'],   [80, '52.9718'],   [82, '52.9718'],   [84, '54.2241'],
        [86, '54.2241'],   [88, '57.7888'],   [90, '57.7888'],   [94, '59.5696'],
        [96, '59.5696'],   [98, '61.4963'],   [100, '61.4963'],  [102, '63.3859'],
        [104, '64.1325'],  [106, '64.1325'],  [108, '64.1325'],  [110, '65.2688'],
        [112, '65.2688'],  [114, '67.2988'],  [116, '67.2588'],  [118, '68.4941'],
        [120, '70.0522'],  [122, '70.0522'],  [124, '70.0522'],  [126, '71.8161'],
        [128, '71.8161'],  [130, '71.8161'],  [132, '73.4452'],  [134, '73.4452'],
        [136, '73.4452'],  [138, '75.2531'],  [140, '75.2531'],  [142, '76.9891'],
        [144, '76.9891'],  [146, '78.8152'],  [148, '78.8152'],  [150, '80.5422'],
        [152, '80.5422'],  [154, '82.1203'],  [156, '82.1203'],  [158, '83.5525'],
        [160, '83.5525'],  [162, '86.1235'],  [164, '86.1235'],  [166, '86.1235'],
        [168, '88.0486'],  [170, '88.0486'],  [172, '88.0486'],  [174, '88.0486'],
        [176, '88.0486'],  [178, '91.5600'],  [180, '91.5600'],  [182, '91.5600'],
        [184, '93.5523'],  [186, '93.5523'],  [188, '95.0528'],  [190, '95.0528'],
        [192, '97.8868'],  [194, '97.8868'],  [196, '97.8868'],  [198, '100.5285'],
        [200, '100.5285'], [202, '100.5285'], [204, '100.5285'], [206, '151.1903'],
        [208, '152.6261'], [210, '154.0195'], [212, '155.4411'], [214, '156.4411'],
        [216, '158.2849'], [218, '159.6925'], [220, '161.1140'], [222, '162.5499'],
        [224, '163.9435'], [226, '177.2653'], [228, '178.8293'], [230, '180.3932'],
        [232, '181.9713'], [234, '183.5353'], [236, '185.0995'], [238, '186.6775'],
        [240, '188.2414'], [242, '189.8053'], [244, '191.3833'], [246, '192.9472'],
        [248, '194.5114'], [250, '196.0895'],
    ];

    $pdo->beginTransaction();
    try {
        $delRates = $pdo->prepare(
            'DELETE FROM `pay_rates`
             WHERE terminal = ? AND trip_type = ? AND stage IN (?, ?)'
        );
        $delRates->execute(['pensacola', 'long_haul', 'default', 'current']);

        $insRate = $pdo->prepare(
            'INSERT INTO `pay_rates` (terminal, trip_type, stage, miles, rate)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($payRates as [$miles, $rate]) {
            $insRate->execute(['pensacola', 'long_haul', 'default', $miles, $rate]);
            $insRate->execute(['pensacola', 'long_haul', 'current', $miles, $rate]);
        }

        $pdo->commit();
        echo "  → pay_rates long_haul refreshed (" . count($payRates) . " tiers × 2 stages)\n";
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

};
