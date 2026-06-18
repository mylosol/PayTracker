<?php
/**
 * @var string                     $base
 * @var string                     $csrfToken
 * @var array<string,mixed>        $actor
 * @var list<array<string,mixed>>  $users
 * @var ?string                    $flash
 * @var bool                       $isSuperAdmin
 * @var array{search:string,role:string,include_spam:bool,per_page:int,page:int} $filters
 * @var array{total:int,shown:int,matching:int,total_pages:int}                   $counts
 */
use PayTracker\Models\Account;

layout('layouts/app');

$buildUrl = static function (array $overrides) use ($base, $filters): string {
    $params = [
        'search'       => $filters['search'],
        'role'         => $filters['role'],
        'include_spam' => $filters['include_spam'] ? '1' : '',
        'per_page'     => (int) $filters['per_page'],
        'page'         => (int) $filters['page'],
    ];
    foreach ($overrides as $k => $v) {
        $params[$k] = is_bool($v) ? ($v ? '1' : '') : (string) $v;
    }
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== 0);
    return $base . '/admin' . ($params === [] ? '' : '?' . http_build_query($params));
};

$fmt = static function ($value): string {
    if (! is_string($value) || $value === '') {
        return '—';
    }
    return $value . ' UTC';
};
?>
<div class="card">
    <h1 class="m-0">Admin Panel
        <span class="pill ok align-middle ml-1 text-xs"><?= e((string) $actor['role']) ?></span>
    </h1>
    <p class="text-brand-muted mt-2">
        Signed in as <strong><?= e((string) $actor['user']) ?></strong> (id <?= (int) $actor['id'] ?>).
        <?php if (! $isSuperAdmin): ?>
            Role assignment is restricted to Super Admin and is hidden
            from this surface for you.
        <?php endif; ?>
    </p>
    <div class="flex flex-wrap gap-2 mt-5">
        <a href="<?= e($base) ?>/admin/audit" class="btn-secondary btn-sm">Audit log →</a>
        <a href="<?= e($base) ?>/admin/diagnostics" class="btn-secondary btn-sm">System diagnostics →</a>
        <a href="<?= e($base) ?>/admin/invites" class="btn-secondary btn-sm">Invite codes →</a>
        <a href="<?= e($base) ?>/admin/terminals" class="btn-secondary btn-sm">Begin Empty Locations →</a>
        <?php if ($isSuperAdmin): ?>
            <a href="<?= e($base) ?>/admin/announcements" class="btn-secondary btn-sm">Announcements →</a>
            <a href="<?= e($base) ?>/admin/reconcile" class="btn-secondary btn-sm">Reconcile queue →</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash !== null): ?>
    <div class="flash-ok break-all" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<div class="card">
    <h2 class="m-0">User accounts
        <span class="block text-sm font-normal text-brand-muted mt-1">
            <?= (int) $counts['shown'] ?> shown of <?= (int) $counts['matching'] ?> matching;
            <?= (int) $counts['total'] ?> total in DB<?= $filters['include_spam'] ? '' : ', spam-handles hidden' ?>
        </span>
    </h2>

    <form method="get" action="<?= e($base) ?>/admin"
          class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:flex-wrap items-end gap-3 mt-4">
        <div class="lg:flex-1 lg:max-w-xs">
            <label for="users-search" class="field-label">Search (user or email)</label>
            <input id="users-search" type="text" name="search" value="<?= e($filters['search']) ?>" maxlength="120"
                   class="field">
        </div>
        <div>
            <label for="users-role" class="field-label">Role</label>
            <select id="users-role" name="role" class="field-select">
                <option value="">Any</option>
                <?php foreach (Account::ROLES as $r): ?>
                    <option value="<?= e($r) ?>" <?= $filters['role'] === $r ? 'selected' : '' ?>>
                        <?= e($r) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="users-pp" class="field-label">Per page</label>
            <select id="users-pp" name="per_page" class="field-select">
                <?php foreach ([25, 50, 100, 200] as $pp): ?>
                    <option value="<?= $pp ?>" <?= (int) $filters['per_page'] === $pp ? 'selected' : '' ?>>
                        <?= $pp ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 min-h-[44px] pb-2 sm:col-span-2 lg:col-auto">
            <input type="checkbox" name="include_spam" value="1" class="field-checkbox"
                   <?= $filters['include_spam'] ? 'checked' : '' ?>>
            <span>Show spam-looking handles</span>
        </label>
        <div class="flex items-center gap-3 sm:col-span-2 lg:col-auto">
            <button type="submit" class="btn-primary btn-sm">Apply</button>
            <a href="<?= e($base) ?>/admin" class="text-sm">Clear</a>
        </div>
    </form>

    <div class="table-wrap mt-5">
        <table class="data-table stack-on-mobile">
            <thead>
                <tr>
                    <th class="hidden 2xl:table-cell">ID</th>
                    <th>User</th>
                    <th>Role</th>
                    <th class="hidden xl:table-cell">Last login</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uId      = (int) $u['id'];
                    $isSelf   = $uId === (int) $actor['id'];
                    $canMutate     = ! $isSelf && Account::canMutate($actor, $u);
                    $canChangeRole = ! $isSelf && Account::canChangeRoleOf($actor, $u);
                    $banned   = is_string($u['banned_at'] ?? null) && $u['banned_at'] !== '';
                    $locked   = is_string($u['locked_until'] ?? null) && $u['locked_until'] !== ''
                                && strtotime((string) $u['locked_until']) > time();
                    $userEmail    = (string) ($u['email'] ?? '—');
                    $lastLoginUtc = $fmt($u['last_login_at'] ?? null);
                    ?>
                    <tr>
                        <td class="hidden 2xl:table-cell"><code><?= $uId ?></code></td>
                        <td class="break-words max-w-xs">
                            <div class="font-medium flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span><?= e((string) $u['user']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="pill ok">you</span>
                                <?php endif; ?>
                                <span class="2xl:hidden text-brand-muted text-xs font-normal">
                                    <code>#<?= $uId ?></code>
                                </span>
                            </div>
                            <div class="text-brand-muted text-xs mt-1 break-all">
                                <?= e($userEmail) ?>
                            </div>
                            <div class="xl:hidden text-brand-muted text-xs mt-1">
                                <span class="md:hidden">Last login: </span><?= e($lastLoginUtc) ?>
                            </div>
                        </td>
                        <td>
                            <?php $currentRole = is_string($u['role'] ?? null) ? (string) $u['role'] : 'user'; ?>
                            <?php if ($isSuperAdmin && $canChangeRole): ?>
                                <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/role"
                                      class="flex flex-wrap items-center gap-1.5">
                                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                    <select name="role" class="field-select text-sm h-9 py-1 px-2 min-h-0 w-auto max-w-[8.5rem]">
                                        <?php foreach (Account::ROLES as $r): ?>
                                            <option value="<?= e($r) ?>" <?= $currentRole === $r ? 'selected' : '' ?>>
                                                <?= e($r) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-secondary btn-sm">Save</button>
                                </form>
                            <?php else: ?>
                                <code><?= e($currentRole) ?></code>
                            <?php endif; ?>
                        </td>
                        <td class="hidden xl:table-cell text-brand-muted text-sm whitespace-nowrap">
                            <?= e($lastLoginUtc) ?>
                        </td>
                        <td>
                            <div class="flex flex-wrap items-center gap-1">
                                <?php if ($banned): ?>
                                    <span class="pill err">banned</span>
                                <?php endif; ?>
                                <?php if ($locked): ?>
                                    <span class="pill warn">locked</span>
                                <?php endif; ?>
                                <?php if (! $banned && ! $locked): ?>
                                    <span class="pill ok">active</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($isSelf): ?>
                                <span class="text-brand-muted text-xs">No self-actions</span>
                            <?php elseif (! $canMutate): ?>
                                <span class="text-brand-muted text-xs">Outranks you</span>
                            <?php else: ?>
                                <div class="flex flex-wrap items-center gap-1.5 max-w-[13rem]">
                                    <a href="<?= e($base) ?>/admin/users/<?= $uId ?>/edit" class="btn-secondary btn-sm">Edit</a>
                                    <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/reset-password" class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button type="submit" class="btn-secondary btn-sm whitespace-nowrap">Reset PW</button>
                                    </form>
                                    <?php if ($banned): ?>
                                        <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/unban" class="inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-emerald-100 text-emerald-900 border border-emerald-200 hover:bg-emerald-200 transition-colors cursor-pointer">
                                                Unban
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/ban"
                                              onsubmit="return confirm('Ban <?= e((string) $u['user']) ?>? They will be logged out and unable to sign in.');"
                                              class="inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center justify-center min-h-[36px] px-3 py-1.5 text-sm rounded-md font-semibold bg-amber-100 text-amber-900 border border-amber-200 hover:bg-amber-200 transition-colors cursor-pointer">
                                                Ban
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="post" action="<?= e($base) ?>/admin/users/<?= $uId ?>/delete"
                                          onsubmit="return confirm('PERMANENTLY DELETE <?= e((string) $u['user']) ?> (id <?= $uId ?>)? This cannot be undone.');"
                                          class="inline">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button type="submit" class="btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ((int) $counts['total_pages'] > 1): ?>
        <?php
        $currentPage = (int) $filters['page'];
        $totalPages  = (int) $counts['total_pages'];
        $prevPage    = max(1, $currentPage - 1);
        $nextPage    = min($totalPages, $currentPage + 1);
        ?>
        <p class="mt-4 flex flex-wrap items-center gap-3 text-sm">
            <?php if ($currentPage > 1): ?>
                <a href="<?= e($buildUrl(['page' => 1])) ?>">« First</a>
                <a href="<?= e($buildUrl(['page' => $prevPage])) ?>">← Prev</a>
            <?php endif; ?>
            <span class="text-brand-muted">Page <?= $currentPage ?> of <?= $totalPages ?></span>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= e($buildUrl(['page' => $nextPage])) ?>">Next →</a>
                <a href="<?= e($buildUrl(['page' => $totalPages])) ?>">Last »</a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>

<div class="card">
    <p class="m-0 text-sm"><a href="<?= e($base) ?>/">← Back home</a></p>
</div>
