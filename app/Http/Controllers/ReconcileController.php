<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\DriverLoad;
use PayTracker\Models\PayReconciliation;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;
use PayTracker\Services\MailService;

/**
 * ReconcileController — driver-facing per-load reconciliation.
 *
 * Each load in the driver's window can be in one of four states:
 *
 *   pending  → no pay_reconciliations row yet. Default for every load
 *              the driver hasn't acted on.
 *   paid     → got the full expected amount.
 *   short    → got LESS than expected. Driver enters the actual figure
 *              and the system computes shortfall = expected - actual.
 *   disputed → formally flagged with a note. Optionally queued for the
 *              next payroll-batch email by ticking "include in batch".
 *
 * Batch notifications:
 *   The driver clicks "Send batch" on this page. ONE email goes to the
 *   address configured in /profile (account.payroll_email). The email
 *   lists every dispute the driver opted in to. Successful send stamps
 *   emailed_at on each row taking it out of the pending pool. Until
 *   Resend DNS verification completes the send returns null and we
 *   surface "delivery pending — try again once DNS verifies" without
 *   touching emailed_at.
 *
 * Window:
 *   The view shows the current pay week (anchored to the driver's
 *   `pay_week_start_day`) plus the previous 4 weeks. Older loads are
 *   reachable via the per-driver dashboard date jumps; this surface
 *   intentionally stays near-term so the list is walkable.
 */
final class ReconcileController extends Controller
{
    /** Number of historical pay weeks shown below the current week. */
    private const HISTORY_WEEKS = 4;

    /** @var array<string,int> 'sun' => 0, 'mon' => 1, …, 'sat' => 6 */
    private const DAY_INDEX = [
        'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3,
        'thu' => 4, 'fri' => 5, 'sat' => 6,
    ];

    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly DriverLoad $loads,
        private readonly PayReconciliation $recon,
        private readonly Account $accounts,
        private readonly AuditLog $audit,
        private readonly MailService $mail,
    ) {
    }

    public function index(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        $driverId        = (int) $account['id'];
        $payWeekStartDay = is_string($account['pay_week_start_day'] ?? null)
            ? (string) $account['pay_week_start_day']
            : 'sun';
        $payrollEmail    = is_string($account['payroll_email'] ?? null) && $account['payroll_email'] !== ''
            ? (string) $account['payroll_email']
            : null;

        // Build the week buckets back from today. Each bucket carries
        // the inclusive (Sun) start + exclusive (next Sun) end the
        // DriverLoad window method expects.
        $weeks = [];
        for ($i = 0; $i <= self::HISTORY_WEEKS; $i++) {
            $weeks[] = $this->weekBucket($payWeekStartDay, $i);
        }

        // One window-query per week. The per-driver volume in the
        // five-week window is small (~50–200 rows), so chasing a
        // single big query + PHP-side grouping isn't worth the
        // extra complexity here.
        $allFrtls = [];
        foreach ($weeks as &$week) {
            $rows = $this->loads->forDriverInWindow($driverId, $week['start_at'], $week['end_at']);
            $week['rows'] = $rows;
            foreach ($rows as $r) {
                $allFrtls[] = (int) $r['frtl'];
            }
        }
        unset($week);

        // Single lookup of reconcile rows across the window so we can
        // attach state inline without re-querying per row.
        $reconByFrtl = $this->recon->byFrtlForDriver($driverId, $allFrtls);

        $pendingBatch = $this->recon->pendingBatchForDriver($driverId);

        return $this->view('reconcile/index', [
            'base'             => $request->basePath(),
            'csrfToken'        => $this->csrf->token(),
            'driver'           => $account,
            'flash'            => $this->popFlash(),
            'weeks'            => $weeks,
            'reconByFrtl'      => $reconByFrtl,
            'pendingBatch'     => $pendingBatch,
            'pendingCount'     => count($pendingBatch),
            'payrollEmail'     => $payrollEmail,
            'mailConfigured'   => $this->mail->isConfigured(),
        ]);
    }

    public function markPaid(Request $request, string $frtl): Response
    {
        return $this->mutate($request, $frtl, function (array $account, int $frtlInt, array $load) {
            $expected = (float) ($load['np'] ?? 0);
            $this->recon->upsert(
                driverId:    (int) $account['id'],
                frtl:        $frtlInt,
                state:       PayReconciliation::STATE_PAID,
                expectedNp:  $expected,
                actualNp:    $expected, // by definition for "paid"
                shortfall:   null,
                note:        null,
                notifyEmail: false,
            );
            $this->audit->record('reconcile.paid', userId: (int) $account['id'], metadata: [
                'frtl'        => $frtlInt,
                'expected_np' => $expected,
            ]);
            return sprintf('Marked load %d paid.', $frtlInt);
        });
    }

    public function markShort(Request $request, string $frtl): Response
    {
        return $this->mutate($request, $frtl, function (array $account, int $frtlInt, array $load) use ($request) {
            $expected = (float) ($load['np'] ?? 0);
            $actualRaw = trim((string) $request->input('actual_np', ''));
            if ($actualRaw === '' || ! is_numeric($actualRaw)) {
                throw new \InvalidArgumentException('Enter the actual amount you were paid (a number).');
            }
            $actual = (float) $actualRaw;
            if ($actual < 0) {
                throw new \InvalidArgumentException('Actual pay cannot be negative.');
            }
            if ($actual > $expected + 0.005) {
                throw new \InvalidArgumentException('Actual pay is greater than expected — use "Mark paid" instead, or flag a dispute if there is a problem.');
            }
            $note = trim((string) $request->input('note', ''));
            $shortfall = round($expected - $actual, 2);
            $this->recon->upsert(
                driverId:    (int) $account['id'],
                frtl:        $frtlInt,
                state:       PayReconciliation::STATE_SHORT,
                expectedNp:  $expected,
                actualNp:    $actual,
                shortfall:   $shortfall,
                note:        $note !== '' ? $note : null,
                notifyEmail: false,
            );
            $this->audit->record('reconcile.short', userId: (int) $account['id'], metadata: [
                'frtl'        => $frtlInt,
                'expected_np' => $expected,
                'actual_np'   => $actual,
                'shortfall'   => $shortfall,
            ]);
            return sprintf('Marked load %d short by $%s.', $frtlInt, number_format($shortfall, 2));
        });
    }

    public function markDisputed(Request $request, string $frtl): Response
    {
        return $this->mutate($request, $frtl, function (array $account, int $frtlInt, array $load) use ($request) {
            $expected = (float) ($load['np'] ?? 0);
            $actualRaw = trim((string) $request->input('actual_np', ''));
            $actual    = null;
            $shortfall = null;
            if ($actualRaw !== '') {
                if (! is_numeric($actualRaw)) {
                    throw new \InvalidArgumentException('Actual pay must be a number (or blank).');
                }
                $actual = (float) $actualRaw;
                if ($actual < 0) {
                    throw new \InvalidArgumentException('Actual pay cannot be negative.');
                }
                $shortfall = round($expected - $actual, 2);
            }
            $note = trim((string) $request->input('note', ''));
            if ($note === '') {
                throw new \InvalidArgumentException('A short note is required for a dispute — what should payroll know?');
            }
            $notify = (string) $request->input('notify_email', '0') === '1';
            $this->recon->upsert(
                driverId:    (int) $account['id'],
                frtl:        $frtlInt,
                state:       PayReconciliation::STATE_DISPUTED,
                expectedNp:  $expected,
                actualNp:    $actual,
                shortfall:   $shortfall,
                note:        $note,
                notifyEmail: $notify,
            );
            $this->audit->record('reconcile.disputed', userId: (int) $account['id'], metadata: [
                'frtl'        => $frtlInt,
                'expected_np' => $expected,
                'actual_np'   => $actual,
                'shortfall'   => $shortfall,
                'notify'      => $notify,
            ]);
            $tail = $notify ? ' (added to the pending payroll batch)' : '';
            return sprintf('Flagged load %d as disputed%s.', $frtlInt, $tail);
        });
    }

    public function undo(Request $request, string $frtl): Response
    {
        return $this->mutate($request, $frtl, function (array $account, int $frtlInt, array $load) {
            $deleted = $this->recon->undo((int) $account['id'], $frtlInt);
            if (! $deleted) {
                throw new \InvalidArgumentException('That load has not been reconciled yet — nothing to undo.');
            }
            $this->audit->record('reconcile.undo', userId: (int) $account['id'], metadata: [
                'frtl' => $frtlInt,
            ]);
            return sprintf('Reset load %d back to pending.', $frtlInt);
        });
    }

    public function toggleNotify(Request $request, string $frtl): Response
    {
        return $this->mutate($request, $frtl, function (array $account, int $frtlInt, array $load) use ($request) {
            $existing = $this->recon->findForLoad((int) $account['id'], $frtlInt);
            if ($existing === null) {
                throw new \InvalidArgumentException('That load has no reconcile row to flag for notification.');
            }
            $notify = (string) $request->input('notify_email', '0') === '1';
            $this->recon->setNotifyFlag((int) $account['id'], $frtlInt, $notify);
            $this->audit->record('reconcile.notify_flag', userId: (int) $account['id'], metadata: [
                'frtl'   => $frtlInt,
                'notify' => $notify,
            ]);
            return $notify
                ? sprintf('Load %d will be included in the next payroll batch.', $frtlInt)
                : sprintf('Load %d removed from the pending payroll batch.', $frtlInt);
        });
    }

    /**
     * POST /reconcile/send-batch — flush every disputed load the driver
     * has marked notify_email=1 + emailed_at=NULL into a single email
     * to their account.payroll_email contact. Returns the count of
     * rows stamped on success; on Resend failure we log the error and
     * surface a flash without touching emailed_at, so the driver can
     * retry once DNS / API issues are resolved.
     */
    public function sendBatch(Request $request): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }

        $driverId     = (int) $account['id'];
        $payrollEmail = is_string($account['payroll_email'] ?? null) ? trim((string) $account['payroll_email']) : '';
        if ($payrollEmail === '' || ! filter_var($payrollEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->failBack($request, 'Set a payroll contact email on your profile before sending a batch.');
        }
        $pending = $this->recon->pendingBatchForDriver($driverId);
        if ($pending === []) {
            return $this->failBack($request, 'No disputes are currently flagged for batch notification.');
        }
        if (! $this->mail->isConfigured()) {
            return $this->failBack($request, 'Email delivery is not configured yet (waiting on RESEND_API + DNS verification). Your disputes are still saved and will go out on the next attempt.');
        }

        [$subject, $html] = $this->buildBatchEmail($account, $pending);

        $messageId = $this->mail->send($payrollEmail, $subject, $html);
        if ($messageId === null) {
            // Resend transport / 4xx failed. We do NOT mark the rows
            // sent so the driver can retry once the underlying issue
            // (DNS verification, invalid sender, etc.) is resolved.
            return $this->failBack($request, 'Could not send the batch email. Often this is DNS verification still pending on the sender domain — try again shortly. Your disputes remain queued.');
        }

        $ids = array_map(static fn ($r) => (int) $r['id'], $pending);
        $stamped = $this->recon->markBatchSent($driverId, $ids);
        $this->audit->record('reconcile.batch_sent', userId: $driverId, metadata: [
            'to'         => $payrollEmail,
            'message_id' => $messageId,
            'count'      => $stamped,
        ]);
        $this->session->put('_flash', sprintf(
            'Sent %d disputed load(s) to %s.',
            $stamped,
            $payrollEmail
        ));
        return $this->redirect($request->basePath() . '/reconcile');
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Shared mutate shell: CSRF check + driver-scoped load lookup +
     * audit-friendly error handling. The callback receives the
     * driver account, the parsed frtl, and the load row; it returns
     * a flash message on success or throws on validation failure.
     */
    private function mutate(Request $request, string $frtl, callable $action): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();
        if (! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request, 'Your session expired. Please try again.');
        }
        if (! ctype_digit($frtl) || (int) $frtl <= 0) {
            return $this->failBack($request, 'That FRTL is not valid.');
        }
        $frtlInt = (int) $frtl;
        $load = $this->loads->findForDriver((int) $account['id'], $frtlInt);
        if ($load === null) {
            return $this->failBack($request, sprintf('Load %d not found on your account.', $frtlInt));
        }
        try {
            $message = $action($account, $frtlInt, $load);
        } catch (\InvalidArgumentException $e) {
            return $this->failBack($request, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->failBack($request, 'Could not save: ' . $e->getMessage());
        }
        $this->session->put('_flash', (string) $message);
        return $this->redirect($request->basePath() . '/reconcile');
    }

    /**
     * Compute the [start, end_exclusive] DATETIME strings for a pay
     * week offset weeks back from today (0 = current week). End is
     * exclusive so the DriverLoad window query is half-open and
     * matches the date column's day boundaries cleanly.
     *
     * @return array{label:string, start:string, end:string, start_at:string, end_at:string}
     */
    private function weekBucket(string $payWeekStartDay, int $weeksBack): array
    {
        $startIndex = self::DAY_INDEX[$payWeekStartDay] ?? 0;
        $today      = new \DateTimeImmutable('today');
        $todayIdx   = (int) $today->format('w'); // 0 = Sunday
        $diff       = ($todayIdx - $startIndex + 7) % 7;
        $thisWeekStart = $today->modify('-' . $diff . ' days');
        $start = $thisWeekStart->modify('-' . (7 * $weeksBack) . ' days');
        $end   = $start->modify('+7 days');
        return [
            'label'    => $start->format('M j') . ' – ' . $end->modify('-1 day')->format('M j'),
            'start'    => $start->format('Y-m-d'),
            'end'      => $end->format('Y-m-d'),
            'start_at' => $start->format('Y-m-d') . ' 00:00:00',
            'end_at'   => $end->format('Y-m-d') . ' 00:00:00',
        ];
    }

    /**
     * Build the subject + HTML body for a dispute batch email.
     *
     * @param array<string,mixed>             $account
     * @param list<array<string,mixed>>       $pending
     * @return array{0:string, 1:string}
     */
    private function buildBatchEmail(array $account, array $pending): array
    {
        $user = (string) ($account['user'] ?? 'driver');
        $count = count($pending);
        $subject = sprintf('PayTracker dispute batch — %d load(s) from %s', $count, $user);

        $rows = '';
        foreach ($pending as $r) {
            $frtl      = (int) ($r['frtl'] ?? 0);
            $expected  = (float) ($r['expected_np'] ?? 0);
            $actual    = isset($r['actual_np'])  && $r['actual_np']  !== null ? (float) $r['actual_np']  : null;
            $shortfall = isset($r['shortfall'])  && $r['shortfall']  !== null ? (float) $r['shortfall']  : null;
            $note      = (string) ($r['note'] ?? '');
            $rows .= sprintf(
                '<tr>'
                . '<td style="padding:6px 10px;border:1px solid #cbd2da;"><code>%d</code></td>'
                . '<td style="padding:6px 10px;border:1px solid #cbd2da;text-align:right;">$%s</td>'
                . '<td style="padding:6px 10px;border:1px solid #cbd2da;text-align:right;">%s</td>'
                . '<td style="padding:6px 10px;border:1px solid #cbd2da;text-align:right;">%s</td>'
                . '<td style="padding:6px 10px;border:1px solid #cbd2da;">%s</td>'
                . '</tr>',
                $frtl,
                number_format($expected, 2),
                $actual    !== null ? '$' . number_format($actual, 2)    : '&mdash;',
                $shortfall !== null ? '$' . number_format($shortfall, 2) : '&mdash;',
                htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        $html = '<div style="font:14px -apple-system,Segoe UI,Roboto,sans-serif;color:#101418;">'
              . '<h2 style="margin:0 0 .6rem 0;">PayTracker dispute batch</h2>'
              . '<p style="margin:0 0 .8rem 0;">Driver <strong>' . htmlspecialchars($user, ENT_QUOTES) . '</strong> '
              . 'has flagged ' . $count . ' load(s) for review:</p>'
              . '<table style="border-collapse:collapse;font-size:13px;">'
              . '<thead><tr>'
              . '<th style="padding:6px 10px;border:1px solid #cbd2da;background:#f1f5f9;">FRTL</th>'
              . '<th style="padding:6px 10px;border:1px solid #cbd2da;background:#f1f5f9;">Expected</th>'
              . '<th style="padding:6px 10px;border:1px solid #cbd2da;background:#f1f5f9;">Actual</th>'
              . '<th style="padding:6px 10px;border:1px solid #cbd2da;background:#f1f5f9;">Shortfall</th>'
              . '<th style="padding:6px 10px;border:1px solid #cbd2da;background:#f1f5f9;">Note</th>'
              . '</tr></thead>'
              . '<tbody>' . $rows . '</tbody></table>'
              . '<p style="color:#5a6470;margin:1rem 0 0 0;font-size:12px;">'
              . 'Sent from PayTracker on behalf of ' . htmlspecialchars($user, ENT_QUOTES) . '. '
              . 'Reply directly to this driver if you need more detail.'
              . '</p></div>';
        return [$subject, $html];
    }

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/reconcile');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
