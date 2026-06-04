<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\AnnouncementDismissal;
use PayTracker\Models\AuditLog;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * AnnouncementController — the lightweight user-facing endpoint
 * for the login-modal "Okay" button.
 *
 * Single route: POST /announcements/{id}/dismiss.
 *
 * Inputs:
 *   _csrf      — required CSRF token
 *   suppress   — '1' if the user ticked "don't show this again"
 *   redirect   — optional safe-relative URL to bounce back to
 *
 * Side effects:
 *   1. recordAck() on the dismissal model (INSERT or UPDATE the
 *      suppressed flag depending on the checkbox).
 *   2. Session flag `announcement_pending` cleared so the modal
 *      vanishes for the rest of the session even when the user
 *      didn't tick "don't show again".
 *   3. Audit row ANNOUNCEMENT_DISMISSED with suppressed value
 *      in the metadata.
 */
final class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AnnouncementDismissal $dismissals,
        private readonly AuditLog $audit,
    ) {
    }

    public function dismiss(Request $request, string $id): Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        $this->session->start();

        if (! $this->csrf->verify($request->input('_csrf'))) {
            // CSRF failure — quietly redirect home without recording.
            // The modal will reappear on the next page load if the
            // session flag is still set; that's the right outcome.
            return $this->redirect($request->basePath() . '/');
        }
        if (! ctype_digit($id) || (int) $id <= 0) {
            return $this->redirect($request->basePath() . '/');
        }

        $announcementId = (int) $id;
        $suppress       = (string) $request->input('suppress', '') === '1';

        try {
            $this->dismissals->recordAck($announcementId, (int) $account['id'], $suppress);
        } catch (\Throwable) {
            // Audit-style swallow: a failed dismissal write must not
            // block the user. They'll see the modal again on next
            // login; that's acceptable degradation.
        }

        $this->audit->record(
            AuditLog::ACTION_ANNOUNCEMENT_DISMISSED,
            userId: (int) $account['id'],
            ipAddress: $this->clientIp(),
            metadata: [
                'announcement_id' => $announcementId,
                'suppressed'      => $suppress,
            ],
        );

        // Clear the session pending flag so the modal vanishes for the
        // rest of this session even when suppress=false.
        $this->session->forget('announcement_pending');

        // Bounce back to a safe relative URL the form supplied; default
        // to home. Reject anything that doesn't begin with '/' so an
        // attacker can't craft an off-site redirect.
        $redirectRaw = (string) $request->input('redirect', '');
        $safeRedirect = $request->basePath() . '/';
        if ($redirectRaw !== '' && str_starts_with($redirectRaw, $request->basePath() . '/')) {
            // Same-app-only redirect.
            $safeRedirect = $redirectRaw;
        }
        return $this->redirect($safeRedirect);
    }

    private function clientIp(): ?string
    {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if ($first !== '') {
                return substr($first, 0, 45);
            }
        }
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($remote) && $remote !== '') {
            return substr($remote, 0, 45);
        }
        return null;
    }
}
