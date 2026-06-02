<?php

declare(strict_types=1);

/**
 * Sync pay_rates and pay_variables to the legacy site's CURRENT values.
 *
 * Why: the original backfill ran at the start of the modernization and
 * pulled whatever was then in PensacolaPayCurrent + variablesCurrent.
 * The legacy site has been updated since (notably: max_* band exists
 * with newBump = 0.2275, and the rate-table tiers shifted upward).
 * Captured fresh JSON exports from the live legacy and embed them
 * here so the migration is reproducible from this commit alone.
 *
 * Scope:
 *   - pay_rates (terminal='pensacola', trip_type='round_trip')
 *       stages 'default' AND 'current' both replaced.
 *   - pay_variables stages 'default' AND 'current' both replaced.
 *
 * NOT touched:
 *   - pay_rates long_haul (no fresh sample pulled — leave as-is)
 *   - pay_rates terminal='panama' (vestigial since the collapse)
 *   - any 'draft' stage (would clobber in-flight admin edits)
 *
 * After this lands, /pay-admin will show the new rates as both
 * default and current with no draft. driver_loads.pay_breakdown
 * for existing loads stays whatever was computed at insert time
 * until a Refresh-my-pay or /pay-admin/recompute runs over them.
 */

return static function (PDO $pdo): void {

// --- 1. Pay rates (round_trip only) ----------------------------------
// Source: live legacy `PensacolaPayCurrent`, exported 2026-06-02.
$payRates = [
    [10, '38.2767'],   [15, '40.5556'],   [20, '42.1367'],   [25, '44.2451'],
    [30, '46.7109'],   [32, '47.7310'],   [34, '49.5884'],   [36, '50.4857'],
    [38, '51.3021'],   [40, '53.1045'],   [42, '54.8727'],   [44, '47.3874'],
    [46, '57.9336'],   [48, '59.7192'],   [50, '61.5216'],   [52, '63.5450'],
    [54, '64.2930'],   [56, '65.4324'],   [58, '67.2688'],   [60, '69.1055'],
    [62, '70.2278'],   [64, '71.9962'],   [66, '73.0843'],   [68, '74.8697'],
    [70, '76.6553'],   [72, '79.2911'],   [74, '80.1920'],   [76, '80.7362'],
    [78, '81.1103'],   [80, '82.9639'],   [82, '84.6983'],   [84, '86.4667'],
    [86, '88.2693'],   [88, '90.0205'],   [90, '91.7889'],   [94, '95.3770'],
    [96, '98.9991'],   [98, '100.8185'],  [100, '101.8385'], [102, '102.3996'],
    [104, '102.6038'], [106, '103.4200'], [108, '104.1343'], [110, '105.9155'],
    [112, '107.6879'], [114, '109.4228'], [116, '111.2081'], [118, '112.9597'],
    [120, '114.6599'], [122, '116.4620'], [124, '118.2137'], [126, '119.9821'],
    [128, '120.5944'], [130, '121.2067'], [132, '122.9409'], [134, '124.6753'],
    [136, '126.3759'], [138, '128.1102'], [140, '129.8617'], [142, '130.3378'],
    [144, '130.7460'], [146, '132.4464'], [148, '134.1637'], [150, '135.3030'],
    [152, '136.0030'], [154, '136.7653'], [156, '138.4490'], [158, '140.1154'],
    [160, '141.8496'], [162, '143.5500'], [164, '145.2164'], [166, '146.9342'],
    [168, '148.6005'], [170, '150.3007'], [172, '151.8824'], [174, '153.6844'],
    [176, '155.3852'], [178, '157.0856'], [180, '158.8028'], [182, '160.4864'],
    [184, '162.1360'], [186, '163.8532'], [188, '165.5538'], [190, '167.2711'],
    [192, '168.9714'], [194, '170.2468'], [196, '172.3555'], [198, '174.0220'],
    [200, '175.7393'], [202, '177.4226'], [204, '179.1232'], [206, '180.8236'],
    [208, '182.5408'], [210, '184.2073'], [212, '185.9076'], [214, '187.5743'],
    [216, '189.3087'], [218, '190.9923'], [220, '192.6923'], [222, '194.4097'],
    [224, '196.0764'], [226, '212.0093'], [228, '213.8798'], [230, '215.7503'],
    [232, '217.6377'], [234, '219.5082'], [236, '221.3790'], [238, '223.2663'],
    [240, '225.1367'], [242, '227.0071'], [244, '228.8944'], [246, '230.7649'],
    [248, '232.6356'], [250, '234.5230'],
];

// --- 2. Pay variables (every key, including max_*) -------------------
// Source: live legacy `variablesCurrent`, exported 2026-06-02.
$payVariables = [
    ['raise',          '0.08675'],
    ['trainer_pay',    '327.75'],
    ['demurrage',      '0.3916666666666667'],
    ['breakdown',      '0.3916666666666667'],
    ['6_mt',           '0.4149'],
    ['6_wk',           '0.11'],
    ['6_newBump',      '0.1075'],
    ['6_night',        '0.15'],
    ['12_mt',          '0.4149'],
    ['12_wk',          '0.11'],
    ['12_newBump',     '0.1075'],
    ['12_night',       '0.15'],
    ['24_mt',          '0.4149'],
    ['24_wk',          '0.115'],
    ['24_newBump',     '0.1075'],
    ['24_night',       '0.16'],
    ['60_mt',          '0.4272'],
    ['60_wk',          '0.12'],
    ['60_newBump',     '0.1275'],
    ['60_night',       '0.16'],
    ['108_mt',         '0.4538'],
    ['108_wk',         '0.1225'],
    ['108_newBump',    '0.1475'],
    ['108_night',      '0.165'],
    ['168_mt',         '0.4962'],
    ['168_wk',         '0.125'],
    ['168_newBump',    '0.1775'],
    ['168_night',      '0.17'],
    ['max_mt',         '0.4962'],
    ['max_wk',         '0.125'],
    ['max_newBump',    '0.2275'],
    ['max_night',      '0.17'],
];

$pdo->beginTransaction();
try {
    // Pay-rates: wipe default + current for (pensacola, round_trip) and
    // reinsert. Draft stage left untouched so an admin's in-flight
    // edit isn't clobbered.
    $delRates = $pdo->prepare(
        'DELETE FROM `pay_rates`
         WHERE terminal = ? AND trip_type = ? AND stage IN (?, ?)'
    );
    $delRates->execute(['pensacola', 'round_trip', 'default', 'current']);

    $insRate = $pdo->prepare(
        'INSERT INTO `pay_rates` (terminal, trip_type, stage, miles, rate)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($payRates as [$miles, $rate]) {
        $insRate->execute(['pensacola', 'round_trip', 'default', $miles, $rate]);
        $insRate->execute(['pensacola', 'round_trip', 'current', $miles, $rate]);
    }

    // Pay-variables: wipe default + current entirely and reinsert.
    // The new `max_*` keys land naturally as part of this.
    $delVars = $pdo->prepare(
        'DELETE FROM `pay_variables` WHERE stage IN (?, ?)'
    );
    $delVars->execute(['default', 'current']);

    $insVar = $pdo->prepare(
        'INSERT INTO `pay_variables` (stage, variable, amount)
         VALUES (?, ?, ?)'
    );
    foreach ($payVariables as [$variable, $amount]) {
        $insVar->execute(['default', $variable, $amount]);
        $insVar->execute(['current', $variable, $amount]);
    }

    $pdo->commit();
    echo "  → pay_rates round_trip refreshed (" . count($payRates) . " tiers × 2 stages)\n";
    echo "  → pay_variables refreshed (" . count($payVariables) . " keys × 2 stages, incl. max_*)\n";
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

};
