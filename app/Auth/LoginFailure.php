<?php

declare(strict_types=1);

namespace PayTracker\Auth;

/**
 * Discriminator returned by AuthService::attempt() on a non-success
 * path. The login form's controller maps each case to a specific
 * user-facing message so banned users see "your account is
 * suspended" -- but ONLY after we've verified they hold the
 * correct password.
 *
 * Why an enum rather than a string code:
 *   - phpstan can prove the controller's switch is exhaustive.
 *   - Adding a new case (e.g. PasswordExpired) becomes a compile-
 *     time discovery exercise, not a runtime surprise.
 *
 * The set is deliberately small. We only enrich the failure
 * surface when revealing the reason is safe given what the
 * caller has already proved:
 *
 *   - BadCredentials: catch-all "no" for every credential-side
 *     failure (wrong password, no such user, account locked
 *     after too many wrong tries). Identical UX to the legacy
 *     "Incorrect login or password" so attackers can't probe
 *     state via the response shape.
 *   - AccountSuspended: ONLY reachable after the password has
 *     been verified. Telling the user "you're suspended" is
 *     safe at that point -- an attacker with the correct
 *     password already has functional access in non-banned
 *     cases, so revealing the suspension flag adds no new
 *     attack surface.
 */
enum LoginFailure: string
{
    case BadCredentials   = 'bad_credentials';
    case AccountSuspended = 'account_suspended';
}
