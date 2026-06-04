<?php

declare(strict_types=1);

/*
 * scripts/set-password.php — seed or reset an account's password.
 *
 * Intended use cases:
 *   - Bootstrap the first admin account on a fresh preview/production
 *     database (no chicken-and-egg: no UI is needed to create the first
 *     login).
 *   - Recover an account that's been locked out (clears the lockout
 *     counters at the same time).
 *   - Rotate a password from the command line without a web round-trip.
 *
 * Usage:
 *   php scripts/set-password.php <user_or_email> <new_password> [--email=foo@bar] [--role=user|admin|super_admin]
 *
 * Safety:
 *   - Refuses to run when APP_ENV=production unless the operator also
 *     passes --confirm-production. Stops you from accidentally rotating
 *     a real user's password while debugging preview.
 *   - Always re-reads the row after writing and verifies the new hash
 *     matches — catches silent write failures (e.g. read-only DB user).
 */

use PayTracker\Auth\PasswordHasher;
use PayTracker\Database\Connection;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "scripts/set-password.php is CLI-only.\n");
    exit(1);
}

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

$args = array_slice($argv, 1);
$flags = array_values(array_filter($args, static fn (string $a): bool => str_starts_with($a, '--')));
$positional = array_values(array_filter($args, static fn (string $a): bool => ! str_starts_with($a, '--')));

if (count($positional) < 2) {
    fwrite(STDERR, "Usage: php scripts/set-password.php <user_or_email> <new_password> [--email=foo] [--role=user|admin|super_admin] [--confirm-production]\n");
    exit(2);
}

[$handle, $newPassword] = $positional;

$opt = static function (string $name) use ($flags): ?string {
    foreach ($flags as $f) {
        if (str_starts_with($f, "--{$name}=")) {
            return substr($f, strlen($name) + 3);
        }
    }
    return null;
};

if (config('app.env') === 'production' && ! in_array('--confirm-production', $flags, true)) {
    fwrite(STDERR, "Refusing to run against APP_ENV=production without --confirm-production.\n");
    exit(3);
}

if (strlen($newPassword) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(4);
}

try {
    /** @var Connection $connection */
    $connection = $app->make(Connection::class);
    $pdo        = $connection->pdo();

    // Locate by either user handle or email — same logic the AuthService
    // uses. Positional placeholders: with EMULATE_PREPARES=false, MySQL
    // native prepared statements don't allow the same named placeholder
    // to appear twice in one query (HY093).
    $find = $pdo->prepare(
        'SELECT id, user FROM `account`
         WHERE user = ? OR (email IS NOT NULL AND email = ?)
         LIMIT 1'
    );
    $find->execute([$handle, $handle]);
    $row = $find->fetch();
    if (! is_array($row)) {
        fwrite(STDERR, "No account found matching `{$handle}`.\n");
        exit(5);
    }

    $hasher = new PasswordHasher();
    $hash   = $hasher->hash($newPassword);

    // Build dynamic UPDATE: always rewrite password_hash + reset lockout
    // counters; optionally update email and role from flags.
    $sets    = ['password_hash = :hash', 'failed_login_count = 0', 'locked_until = NULL'];
    $bindings = ['hash' => $hash, 'id' => (int) $row['id']];

    if (($email = $opt('email')) !== null) {
        $sets[]            = 'email = :email';
        $bindings['email'] = $email;
    }
    if (($role = $opt('role')) !== null) {
        $sets[]           = 'role = :role';
        $bindings['role'] = $role;
    }

    $sql = 'UPDATE `account` SET ' . implode(', ', $sets) . ' WHERE id = :id';
    $upd = $pdo->prepare($sql);
    $upd->execute($bindings);

    // Read-back verification.
    $check = $pdo->prepare('SELECT password_hash FROM `account` WHERE id = :id LIMIT 1');
    $check->execute(['id' => $row['id']]);
    $stored = $check->fetchColumn();
    if (! is_string($stored) || ! $hasher->verify($newPassword, $stored)) {
        fwrite(STDERR, "Write appeared to succeed but the new hash does not verify — investigate immediately.\n");
        exit(6);
    }

    echo "Updated password for account id={$row['id']} (user={$row['user']}).\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf("Failed: %s\n", $e->getMessage()));
    exit(1);
}
