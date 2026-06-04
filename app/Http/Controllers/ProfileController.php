<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * ProfileController — driver profile page. Two fields today:
 *
 *   hire_date  → drives tenure-band selection in PayCalculator (the
 *                weeks-since-hire is computed at load-write time and
 *                snapshotted into driver_loads.variables).
 *
 *   shift      → 'day' or 'night'. Snapshotted into every new load's
 *                variables blob.
 *
 * Both are per-driver; admins don't edit other drivers' profiles from
 * this surface. The /pay-admin surface stays for global rate config.
 *
 * Why these matter: until this page existed, every new load got a
 * hardcoded '168-night--0' variables blob. Drivers below the senior
 * band had inflated np and day-shift drivers got a phantom night
 * bonus. The profile values close that gap on every NEW load. Old
 * loads keep their historical (legacy) blob — recomputing them with
 * the new profile would silently revalue history and we don't want
 * that.
 */
final class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly Account $accounts,
    ) {
    }

    public function show(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        return $this->view('profile/index', [
            'base'             => $request->basePath(),
            'csrfToken'        => $this->csrf->token(),
            'driver'           => $account,
            'username'         => is_string($account['user']  ?? null) ? (string) $account['user']  : '',
            'email'            => is_string($account['email'] ?? null) ? (string) $account['email'] : '',
            'hireDate'         => is_string($account['hire_date'] ?? null) ? (string) $account['hire_date'] : '',
            'shift'            => is_string($account['shift'] ?? null) ? (string) $account['shift'] : 'day',
            'payWeekStartDay'  => is_string($account['pay_week_start_day'] ?? null) ? (string) $account['pay_week_start_day'] : 'sun',
            'flash'            => $this->popFlash(),
        ]);
    }

    public function save(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        $userRaw      = trim((string) $request->input('username', ''));
        $emailRaw     = trim((string) $request->input('email', ''));
        $hireRaw      = trim((string) $request->input('hire_date', ''));
        $shiftRaw     = (string) $request->input('shift', 'day');
        $payWeekRaw   = (string) $request->input('pay_week_start_day', 'sun');

        // Username + email: validated and persisted via Account::updateBasics
        // which enforces the charset rule (USER_PATTERN, no '@' allowed),
        // email format, and application-level uniqueness against the
        // other accounts. Run this BEFORE updateProfile so a uniqueness
        // collision doesn't leave the row partially updated.
        try {
            $this->accounts->updateBasics(
                (int) $account['id'],
                $userRaw,
                $emailRaw === '' ? null : $emailRaw
            );
        } catch (\Throwable $e) {
            return $this->failBack($request, $e->getMessage());
        }

        // hire_date is optional — drivers who haven't filled it in keep
        // the legacy fallback (senior band). When supplied it must be a
        // calendar date not in the future.
        $hireDate = null;
        if ($hireRaw !== '') {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireRaw) !== 1) {
                return $this->failBack($request, 'Hire date must be in YYYY-MM-DD format.');
            }
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $hireRaw);
            if ($parsed === false || $parsed->format('Y-m-d') !== $hireRaw) {
                return $this->failBack($request, 'Hire date is not a valid calendar date.');
            }
            if ($parsed > new \DateTimeImmutable('tomorrow')) {
                return $this->failBack($request, 'Hire date cannot be in the future.');
            }
            $hireDate = $hireRaw;
        }

        if (! in_array($shiftRaw, ['day', 'night'], true)) {
            return $this->failBack($request, 'Shift must be Day or Night.');
        }
        if (! in_array($payWeekRaw, Account::PAY_WEEK_DAYS, true)) {
            return $this->failBack($request, 'Pay week start day must be a valid weekday.');
        }

        try {
            $this->accounts->updateProfile((int) $account['id'], $hireDate, $shiftRaw, $payWeekRaw);
        } catch (\Throwable $e) {
            return $this->failBack($request, 'Could not save profile: ' . $e->getMessage());
        }

        $this->session->put('_flash', sprintf(
            'Profile saved. Username: %s. Email: %s. Tenure date: %s. Shift: %s. Pay week starts %s.',
            $userRaw,
            $emailRaw === '' ? '(unset)' : $emailRaw,
            $hireDate ?? 'unset (junior-band default)',
            ucfirst($shiftRaw),
            ucfirst($payWeekRaw)
        ));
        return $this->redirect($request->basePath() . '/profile');
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/profile');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
