<?php
/**
 * @var string $base
 * @var string $csrfToken
 * @var array<string,string> $current       variable → amount, live
 * @var array<string,string> $draft         variable → amount, working copy
 * @var array<string,string> $defaultVars   variable → amount, factory baseline
 * @var bool                 $hasDraft
 * @var list<string>         $globalKeys    globals in intended display order
 * @var array<string,string> $globalLabels  variable → human label
 * @var list<string>         $bands         tenure bands ('6', '12', …, 'max')
 * @var list<string>         $suffixes      per-band suffixes ('mt', 'newBump', 'night', 'wk', 'tb')
 * @var string|null          $flash
 */
layout('layouts/app');

/**
 * Small helper: render one editable variable row.
 * @param array<string,string> $current
 * @param array<string,string> $draft
 * @param array<string,string> $defaultVars
 */
$row = static function (
    string $variable,
    string $label,
    array $current,
    array $draft,
    array $defaultVars,
    string $csrfToken,
    string $base,
    string $note = '',
): string {
    $cur = $current[$variable]   ?? '';
    $drf = $draft[$variable]     ?? '';
    $def = $defaultVars[$variable] ?? '';
    $val = $drf !== '' ? $drf : $cur;
    $isDrift = $drf !== '' && $drf !== $cur;
    $curDisplay = $cur === '' ? '<em class="text-brand-muted">unset</em>' : '<code>' . e($cur) . '</code>';
    $defDisplay = $def === '' ? '<em class="text-brand-muted">unset</em>' : '<code>' . e($def) . '</code>';

    $out  = '<tr' . ($isDrift ? ' class="bg-amber-50 dark:bg-amber-950/30"' : '') . '>';
    $out .= '<td data-label="Variable"><code>' . e($variable) . '</code>'
          . ($label !== $variable ? '<div class="text-xs text-brand-muted">' . e($label) . '</div>' : '')
          . '</td>';
    $out .= '<td data-label="Current" class="md:text-right whitespace-nowrap">' . $curDisplay . '</td>';
    $out .= '<td data-label="Default" class="md:text-right whitespace-nowrap">' . $defDisplay . '</td>';
    $out .= '<td data-label="Draft / save">'
          . '<form method="post" action="' . e($base) . '/pay-admin/variables/draft/upsert" class="flex flex-wrap items-center gap-2 m-0">'
          . '<input type="hidden" name="_csrf" value="' . e($csrfToken) . '">'
          . '<input type="hidden" name="variable" value="' . e($variable) . '">'
          . '<input type="text" name="amount" inputmode="decimal" required value="' . e($val) . '" class="field w-28">'
          . '<button type="submit" class="btn-primary btn-sm">Save</button>'
          . '</form>'
          . ($note !== '' ? '<div class="text-xs text-brand-muted mt-1">' . e($note) . '</div>' : '')
          . '</td>';
    $out .= '</tr>';
    return $out;
};

$notes = [
    'raise'       => 'Applied to every loaded rate: loaded_pay = bracket × (1 + raise). 0.16 = 16%.',
    'trainer_pay' => 'Flat amount for load_type 4 (trainer).',
    'demurrage'   => 'Dollars per minute for dem_minutes.',
    'breakdown'   => 'Dollars per minute for break_minutes.',
];

$bandNotes = [
    'mt'      => 'Empty-mile rate ($/mile).',
    'newBump' => 'Seniority multiplier — 0.2275 = 22.75%.',
    'night'   => 'Night-shift multiplier — applied only on night-shift loads.',
    'wk'      => 'Weekend multiplier — applied only on is_weekend loads.',
    'tb'      => 'Historical tenure bump; queried by legacy but not used by the formula.',
];
?>
<div class="card">
    <h1 class="m-0">Pay-variable admin</h1>
    <p class="text-brand-muted mt-2">
        Global constants the formula multiplies against bracket rates.
        Edit a draft, promote it to current. Percent-style values are
        stored as decimals — <code>0.1627</code> means 16.27%, not
        1627%. Historical loads keep the pay stored when they were
        entered; run
        <a href="<?= e($base) ?>/pay-admin#recompute-pay">Recompute pay</a>
        after a promote to refresh them.
    </p>
    <p class="text-sm mt-3 flex flex-wrap gap-3 items-center">
        <a href="<?= e($base) ?>/pay-admin" class="btn-secondary btn-sm">← Back to pay-admin</a>
        <span class="text-brand-muted">Draft: <code><?= $hasDraft ? count($draft) . ' rows' : 'none' ?></code></span>
    </p>

    <?php if ($flash !== null): ?>
        <div class="flash-ok mt-4" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <div class="flex flex-col gap-4 mt-4">
        <?php if (! $hasDraft): ?>
            <div class="flex flex-wrap gap-2 items-center">
                <form method="post" action="<?= e($base) ?>/pay-admin/variables/draft/start" class="m-0">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn-primary btn-sm">
                        Start draft from current
                    </button>
                </form>
                <span class="text-xs text-brand-muted">
                    Saving any variable below auto-starts a draft; this button just makes the copy explicit.
                </span>
            </div>
        <?php else: ?>
            <div class="flex flex-wrap gap-2 items-center">
                <form method="post" action="<?= e($base) ?>/pay-admin/variables/draft/promote" class="m-0"
                      onsubmit="return confirm('Promote variables draft → current? Every load computed AFTER this uses the new values. Historical stored pay is unchanged until you run Recompute pay.');">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn-primary btn-sm">
                        Promote draft → current
                    </button>
                </form>
                <form method="post" action="<?= e($base) ?>/pay-admin/variables/draft/start" class="m-0"
                      onsubmit="return confirm('Discard the current variables draft and start fresh from current?');">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" class="btn-secondary btn-sm">
                        Reset draft to current
                    </button>
                </form>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2 items-center pt-2 border-t border-slate-200 dark:border-slate-700">
            <form method="post" action="<?= e($base) ?>/pay-admin/variables/reset" class="m-0"
                  onsubmit="return confirm('Reset variables to factory defaults? Clears any draft. Irreversible from the UI.');">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" class="btn-danger btn-sm">
                    Reset variables ← default
                </button>
            </form>
            <span class="text-xs text-brand-muted">
                Rolls current back to what shipped with the app.
            </span>
        </div>
    </div>
</div>

<div class="card">
    <h2 class="m-0">Global variables</h2>
    <p class="text-brand-muted mt-2 mb-4 text-sm">
        Draft rows that differ from current are highlighted amber.
    </p>
    <div class="table-wrap">
        <table class="data-table stack-on-mobile text-[14px]">
            <thead>
                <tr>
                    <th>Variable</th>
                    <th class="text-right">Current</th>
                    <th class="text-right">Default</th>
                    <th>Draft / save</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($globalKeys as $key): ?>
                    <?= $row(
                        $key,
                        $globalLabels[$key] ?? $key,
                        $current,
                        $draft,
                        $defaultVars,
                        $csrfToken,
                        $base,
                        $notes[$key] ?? '',
                    ) ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($bands as $band): ?>
    <div class="card">
        <h2 class="m-0">Tenure band <code><?= e($band) ?></code></h2>
        <p class="text-brand-muted mt-2 mb-4 text-sm">
            Overlays for drivers in the <code><?= e($band) ?></code> band. Same
            row shape as globals — draft cells that differ from current are
            highlighted.
        </p>
        <div class="table-wrap">
            <table class="data-table stack-on-mobile text-[14px]">
                <thead>
                    <tr>
                        <th>Variable</th>
                        <th class="text-right">Current</th>
                        <th class="text-right">Default</th>
                        <th>Draft / save</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($suffixes as $suffix):
                        $key = $band . '_' . $suffix;
                    ?>
                        <?= $row(
                            $key,
                            $suffix,
                            $current,
                            $draft,
                            $defaultVars,
                            $csrfToken,
                            $base,
                            $bandNotes[$suffix] ?? '',
                        ) ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
