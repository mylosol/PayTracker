<?php

declare(strict_types=1);

namespace PayTracker\Auth;

/**
 * Thin wrapper around PHP's native password_hash / password_verify.
 *
 * Why a wrapper:
 *   - Pins the algorithm (`PASSWORD_BCRYPT`) and cost so every hash in the
 *     database uses the same parameters at the time it was written. PHP's
 *     default algorithm has changed across versions (BCRYPT → ARGON2I) and
 *     letting that drift silently makes hash rotations a guessing game.
 *   - Centralises the `needsRehash` check so we can opportunistically
 *     re-hash on login when we bump the cost factor in future.
 *
 * Cost factor 12 is the sane default for shared hosting in 2026 — ~250 ms
 * per hash on the DreamHost CPU we saw in the smoke test. If login latency
 * becomes an issue we'll lower it, but that's the kind of thing we want to
 * tune once we have real measurements rather than guess upfront.
 */
final class PasswordHasher
{
    private const ALGO = PASSWORD_BCRYPT;
    private const COST = 12;

    public function hash(string $plain): string
    {
        return password_hash($plain, self::ALGO, ['cost' => self::COST]);
    }

    public function verify(string $plain, string $hash): bool
    {
        // `password_verify` is constant-time — safe against timing attacks
        // even when the supplied hash is malformed or empty.
        return $hash !== '' && password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::ALGO, ['cost' => self::COST]);
    }
}
