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
}
