<?php

declare(strict_types=1);

namespace PayTracker\Tests\Unit\Models;

use PayTracker\Models\Account;
use PHPUnit\Framework\TestCase;

/**
 * Closed-form unit tests for Account::hasRole — the RBAC privilege
 * comparison the base Controller depends on.
 *
 * The hierarchy is encoded by ordering in Account::ROLES, so the
 * tests treat that constant as the source of truth: if anyone
 * adds a new role between 'user' and 'admin' the existing tests
 * still pin the correct behaviour around the surrounding rungs.
 */
final class AccountTest extends TestCase
{
    public function testNullAccountNeverHasAnyRole(): void
    {
        $this->assertFalse(Account::hasRole(null, Account::ROLE_USER));
        $this->assertFalse(Account::hasRole(null, Account::ROLE_ADMIN));
        $this->assertFalse(Account::hasRole(null, Account::ROLE_SUPER_ADMIN));
    }

    public function testExactRoleMatchesItself(): void
    {
        $this->assertTrue(Account::hasRole(['role' => 'user'],        Account::ROLE_USER));
        $this->assertTrue(Account::hasRole(['role' => 'admin'],       Account::ROLE_ADMIN));
        $this->assertTrue(Account::hasRole(['role' => 'super_admin'], Account::ROLE_SUPER_ADMIN));
    }

    public function testHigherRoleSatisfiesLowerRequirement(): void
    {
        // admin >= user, super_admin >= admin >= user
        $this->assertTrue(Account::hasRole(['role' => 'admin'],       Account::ROLE_USER));
        $this->assertTrue(Account::hasRole(['role' => 'super_admin'], Account::ROLE_USER));
        $this->assertTrue(Account::hasRole(['role' => 'super_admin'], Account::ROLE_ADMIN));
    }

    public function testLowerRoleFailsHigherRequirement(): void
    {
        // user does not satisfy admin or super_admin
        $this->assertFalse(Account::hasRole(['role' => 'user'],  Account::ROLE_ADMIN));
        $this->assertFalse(Account::hasRole(['role' => 'user'],  Account::ROLE_SUPER_ADMIN));
        $this->assertFalse(Account::hasRole(['role' => 'admin'], Account::ROLE_SUPER_ADMIN));
    }

    public function testMissingRoleKeyFailsClosed(): void
    {
        // An account row that somehow lacks the role column at all
        // is treated as least-privileged; we never grant access on
        // a missing claim. (DB DEFAULT 'user' makes this fairly
        // theoretical but the guard belongs in code.)
        $this->assertFalse(Account::hasRole([], Account::ROLE_USER));
        $this->assertFalse(Account::hasRole(['id' => 1], Account::ROLE_USER));
    }

    public function testUnknownRoleStringFailsClosed(): void
    {
        // A role string outside the whitelist (e.g. legacy 'guest',
        // a typo, or hand-mutated DB row) gets no privilege at all.
        $this->assertFalse(Account::hasRole(['role' => 'guest'],     Account::ROLE_USER));
        $this->assertFalse(Account::hasRole(['role' => 'Super'],     Account::ROLE_ADMIN));
        $this->assertFalse(Account::hasRole(['role' => ''],          Account::ROLE_USER));
    }

    public function testUnknownMinimumRoleThrows(): void
    {
        // Asking "does this account have role X" where X isn't a real
        // role is a programmer error — surface it loudly rather than
        // silently allowing anything.
        $this->expectException(\InvalidArgumentException::class);
        Account::hasRole(['role' => 'super_admin'], 'unknown_role');
    }

    // ===== canMutate -- privilege-chain mutation guard =================

    public function testCanMutateRequiresStrictlyHigherRole(): void
    {
        $user       = ['role' => 'user'];
        $admin      = ['role' => 'admin'];
        $superAdmin = ['role' => 'super_admin'];

        // Higher acts on lower: yes.
        $this->assertTrue(Account::canMutate($admin,      $user));
        $this->assertTrue(Account::canMutate($superAdmin, $user));
        $this->assertTrue(Account::canMutate($superAdmin, $admin));

        // Peers: no. Two same-tier actors cannot reach each other --
        // no super-admin power struggle, no peer-admin sniping.
        $this->assertFalse(Account::canMutate($user,       $user));
        $this->assertFalse(Account::canMutate($admin,      $admin));
        $this->assertFalse(Account::canMutate($superAdmin, $superAdmin));

        // Lower acts on higher: no. Even if the UI somehow surfaced
        // a button, the server-side guard refuses.
        $this->assertFalse(Account::canMutate($user,  $admin));
        $this->assertFalse(Account::canMutate($user,  $superAdmin));
        $this->assertFalse(Account::canMutate($admin, $superAdmin));
    }

    public function testCanMutateFailsClosedOnNullsAndUnknownRoles(): void
    {
        $superAdmin = ['role' => 'super_admin'];
        $user       = ['role' => 'user'];

        $this->assertFalse(Account::canMutate(null,        $user));
        $this->assertFalse(Account::canMutate($superAdmin, null));
        $this->assertFalse(Account::canMutate(null,        null));

        // Unknown role values get no privilege at all.
        $this->assertFalse(Account::canMutate(['role' => 'guest'], $user));
        $this->assertFalse(Account::canMutate($superAdmin,         ['role' => 'guest']));
        $this->assertFalse(Account::canMutate([],                  $user));
    }
}
