<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\Announcement;
use PayTracker\Models\AnnouncementDismissal;
use PayTracker\Models\AuditLog;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * AdminAnnouncementsController — Super-Admin-only CRUD for the
 * login-modal announcement surface.
 *
 * Routes (super_admin):
 *   GET  /admin/announcements                    — list everything
 *   GET  /admin/announcements/new                — create form
 *   POST /admin/announcements                    — create
 *   GET  /admin/announcements/{id}               — view + "seen by" report
 *   GET  /admin/announcements/{id}/edit          — edit form
 *   POST /admin/announcements/{id}/edit          — update basics
 *   POST /admin/announcements/{id}/activate      — make this the active row
 *   POST /admin/announcements/{id}/deactivate    — clear is_active
 *   POST /admin/announcements/{id}/use-template  — clone a template into a new active row
 *   POST /admin/announcements/{id}/delete        — hard delete (cascades dismissals via NOT-cascade left join)
 *
 * Why Super-Admin only: announcements are seen by every user on
 * every login. Mis-broadcasting one with a typo or stale info
 * has site-wide blast radius. Gating at the highest tier inside
 * the admin panel matches the role-assignment posture.
 *
 * Every mutate path writes an audit_logs row so the trail at
 * /admin/audit attributes site-wide messages.
 */
final class AdminAnnouncementsController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly Announcement $announcements,
        private readonly AnnouncementDismissal $dismissals,
        private readonly AuditLog $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        $rows = $this->announcements->allForAdmin();
        // Attach viewer count so the list view shows "12 users dismissed"
        // alongside each row without N+1 queries on the dismissal table —
        // the count() is cheap and the row volume here is small (a single
        // admin surface).
        foreach ($rows as &$row) {
            $row['viewer_count'] = $this->dismissals->viewerCount((int) $row['id']);
        }
        unset($row);

        return $this->view('admin/announcements/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'rows'      => $rows,
            'flash'     => $this->popFlash(),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        return $this->view('admin/announcements/new', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'flash'     => $this->popFlash(),
            'subjectMax'=> Announcement::MAX_SUBJECT_LEN,
            'bodyMax'   => Announcement::MAX_BODY_LEN,
            'old'       => [
                'subject'     => $this->session->get('_old_ann_subject')    ?? '',
                'body'        => $this->session->get('_old_ann_body')       ?? '',
                'expires_at'  => $this->session->get('_old_ann_expires_at') ?? '',
                'is_template' => $this->session->get('_old_ann_template')   ?? '',
                'activate'    => $this->session->get('_old_ann_activate')   ?? '',
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $actor = $this->auth->currentAccount();
        // $actor is non-null here -- gate() already verified.

        $subject     = trim((string) $request->input('subject', ''));
        $body        = trim((string) $request->input('body', ''));
        $expiresAt   = trim((string) $request->input('expires_at', ''));
        $isTemplate  = (string) $request->input('is_template', '') === '1';
        $alsoActivate= (string) $request->input('activate', '') === '1';

        $this->session->put('_old_ann_subject',     $subject);
        $this->session->put('_old_ann_body',        $body);
        $this->session->put('_old_ann_expires_at',  $expiresAt);
        $this->session->put('_old_ann_template',    $isTemplate ? '1' : '');
        $this->session->put('_old_ann_activate',    $alsoActivate ? '1' : '');

        if (($err = $this->validateBasics($subject, $body, $expiresAt)) !== null) {
            return $this->failBack($request->basePath() . '/admin/announcements/new', $err);
        }
        $normalizedExpiry = $this->normalizeExpiry($expiresAt);

        try {
            $newId = $this->announcements->create(
                $subject,
                $body,
                $normalizedExpiry,
                (int) ($actor['id'] ?? 0),
                $isTemplate
            );
            if ($alsoActivate && ! $isTemplate) {
                $this->announcements->activate($newId);
            }
        } catch (\Throwable $e) {
            return $this->failBack($request->basePath() . '/admin/announcements/new', 'Could not save: ' . $e->getMessage());
        }

        $this->audit->record(
            AuditLog::ACTION_ANNOUNCEMENT_CREATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'announcement_id' => $newId,
                'subject'         => $subject,
                'is_template'     => $isTemplate,
                'activated'       => $alsoActivate && ! $isTemplate,
                'expires_at'      => $normalizedExpiry,
            ],
        );

        foreach (['_old_ann_subject','_old_ann_body','_old_ann_expires_at','_old_ann_template','_old_ann_activate'] as $k) {
            $this->session->forget($k);
        }

        $this->session->put('_flash', sprintf(
            '%s "%s" (id %d)%s.',
            $isTemplate ? 'Saved template' : 'Created announcement',
            $subject,
            $newId,
            $alsoActivate && ! $isTemplate ? ' and activated' : ''
        ));
        return $this->redirect($request->basePath() . '/admin/announcements');
    }

    public function show(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        return $this->view('admin/announcements/show', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'row'       => $row,
            'viewers'   => $this->dismissals->viewersOf((int) $row['id']),
            'flash'     => $this->popFlash(),
        ]);
    }

    public function editForm(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        return $this->view('admin/announcements/edit', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'row'       => $row,
            'flash'     => $this->popFlash(),
            'subjectMax'=> Announcement::MAX_SUBJECT_LEN,
            'bodyMax'   => Announcement::MAX_BODY_LEN,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        $subject     = trim((string) $request->input('subject', ''));
        $body        = trim((string) $request->input('body', ''));
        $expiresAt   = trim((string) $request->input('expires_at', ''));
        $isTemplate  = (string) $request->input('is_template', '') === '1';

        if (($err = $this->validateBasics($subject, $body, $expiresAt)) !== null) {
            return $this->failBack(
                $request->basePath() . '/admin/announcements/' . (int) $row['id'] . '/edit',
                $err
            );
        }

        try {
            $this->announcements->updateBasics(
                (int) $row['id'],
                $subject,
                $body,
                $this->normalizeExpiry($expiresAt),
                $isTemplate
            );
        } catch (\Throwable $e) {
            return $this->failBack(
                $request->basePath() . '/admin/announcements/' . (int) $row['id'] . '/edit',
                'Could not save: ' . $e->getMessage()
            );
        }

        $this->audit->record(
            AuditLog::ACTION_ANNOUNCEMENT_UPDATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'announcement_id' => (int) $row['id'],
                'subject'         => $subject,
                'is_template'     => $isTemplate,
            ],
        );

        $this->session->put('_flash', sprintf('Updated announcement "%s" (id %d).', $subject, (int) $row['id']));
        return $this->redirect($request->basePath() . '/admin/announcements');
    }

    public function activate(Request $request, string $id): Response
    {
        return $this->simpleAction($request, $id, 'activate', function (array $actor, array $row): string {
            $this->announcements->activate((int) $row['id']);
            $this->audit->record(
                AuditLog::ACTION_ANNOUNCEMENT_ACTIVATED,
                userId: (int) ($actor['id'] ?? 0),
                ipAddress: $this->clientIp(),
                metadata: ['announcement_id' => (int) $row['id'], 'subject' => (string) $row['subject']],
            );
            return sprintf('Activated "%s" — every other announcement is now inactive.', (string) $row['subject']);
        });
    }

    public function deactivate(Request $request, string $id): Response
    {
        return $this->simpleAction($request, $id, 'deactivate', function (array $actor, array $row): string {
            $this->announcements->deactivate((int) $row['id']);
            $this->audit->record(
                AuditLog::ACTION_ANNOUNCEMENT_DEACTIVATED,
                userId: (int) ($actor['id'] ?? 0),
                ipAddress: $this->clientIp(),
                metadata: ['announcement_id' => (int) $row['id'], 'subject' => (string) $row['subject']],
            );
            return sprintf('Deactivated "%s".', (string) $row['subject']);
        });
    }

    public function useTemplate(Request $request, string $id): Response
    {
        return $this->simpleAction($request, $id, 'use-template', function (array $actor, array $row): string {
            if ((int) ($row['is_template'] ?? 0) !== 1) {
                throw new \RuntimeException('That row is not flagged as a template.');
            }
            $newId = $this->announcements->create(
                (string) $row['subject'],
                (string) $row['body'],
                null, // copy without expiry; admin can set one via edit afterward
                (int) ($actor['id'] ?? 0),
                false // the clone is NOT a template; the original stays as one
            );
            $this->announcements->activate($newId);
            $this->audit->record(
                AuditLog::ACTION_ANNOUNCEMENT_CREATED,
                userId: (int) ($actor['id'] ?? 0),
                ipAddress: $this->clientIp(),
                metadata: [
                    'announcement_id' => $newId,
                    'subject'         => (string) $row['subject'],
                    'from_template_id'=> (int) $row['id'],
                    'activated'       => true,
                ],
            );
            return sprintf('Cloned template "%s" into announcement %d and activated it.', (string) $row['subject'], $newId);
        });
    }

    public function delete(Request $request, string $id): Response
    {
        return $this->simpleAction($request, $id, 'delete', function (array $actor, array $row): string {
            $this->announcements->delete((int) $row['id']);
            $this->audit->record(
                AuditLog::ACTION_ANNOUNCEMENT_DELETED,
                userId: (int) ($actor['id'] ?? 0),
                ipAddress: $this->clientIp(),
                metadata: ['announcement_id' => (int) $row['id'], 'subject' => (string) $row['subject']],
            );
            return sprintf('Deleted announcement "%s" (id %d).', (string) $row['subject'], (int) $row['id']);
        });
    }

    // ====================================================================
    // Internal plumbing
    // ====================================================================

    /**
     * Auth + super_admin RBAC gate, plus optional CSRF verification
     * for POSTs. Returns null on pass; a redirect or 403 Response
     * on fail. Centralises the four checks every action repeats.
     */
    private function gate(Request $request, bool $csrfCheck = false): ?Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_SUPER_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();
        if ($csrfCheck && ! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack(
                $request->basePath() . '/admin/announcements',
                'Your session expired. Please try again.'
            );
        }
        return null;
    }

    /**
     * Shared shell for POST endpoints that don't take form fields
     * beyond the id (activate / deactivate / use-template / delete).
     * Mirrors the AdminUsersController::mutate pattern.
     *
     * @param callable(array<string,mixed>, array<string,mixed>): string $body
     */
    private function simpleAction(Request $request, string $idRaw, string $verb, callable $body): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $idRaw);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();
        try {
            $message = $body($actor ?? [], $row);
        } catch (\Throwable $e) {
            return $this->failBack($request->basePath() . '/admin/announcements', $verb . ' failed: ' . $e->getMessage());
        }
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/admin/announcements');
    }

    /**
     * @return array<string,mixed>|Response
     */
    private function loadOrRedirect(Request $request, string $idRaw): array|Response
    {
        if (! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            return $this->failBack($request->basePath() . '/admin/announcements', 'Invalid announcement id.');
        }
        $row = $this->announcements->findById((int) $idRaw);
        if ($row === null) {
            return $this->failBack(
                $request->basePath() . '/admin/announcements',
                sprintf('No announcement with id %d.', (int) $idRaw)
            );
        }
        return $row;
    }

    /**
     * Subject + body length / required checks; expiry format
     * verification. Returns null on success or the user-facing
     * error string on failure.
     */
    private function validateBasics(string $subject, string $body, string $expiresAt): ?string
    {
        if ($subject === '') {
            return 'Subject is required.';
        }
        if (mb_strlen($subject) > Announcement::MAX_SUBJECT_LEN) {
            return sprintf('Subject is too long (max %d chars).', Announcement::MAX_SUBJECT_LEN);
        }
        if ($body === '') {
            return 'Body is required.';
        }
        if (mb_strlen($body) > Announcement::MAX_BODY_LEN) {
            return sprintf('Body is too long (max %d chars).', Announcement::MAX_BODY_LEN);
        }
        if ($expiresAt !== '') {
            // Accept the <input type="datetime-local"> format
            // YYYY-MM-DDTHH:MM (no seconds, no timezone) or
            // YYYY-MM-DD HH:MM[:SS].
            $patterns = [
                '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/',
            ];
            $ok = false;
            foreach ($patterns as $p) {
                if (preg_match($p, $expiresAt) === 1) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                return 'Expiry must be YYYY-MM-DDTHH:MM (datetime-local) or YYYY-MM-DD HH:MM[:SS].';
            }
        }
        return null;
    }

    /**
     * Convert the form's datetime-local value (YYYY-MM-DDTHH:MM
     * in APP_TIMEZONE local time) to the UTC DATETIME format
     * MySQL stores. Empty input → null (= "no expiry"). Centralised
     * in local_input_to_utc() so the matching invite-codes surface
     * uses the same conversion.
     */
    private function normalizeExpiry(string $expiresAt): ?string
    {
        return local_input_to_utc($expiresAt);
    }

    private function failBack(string $url, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($url);
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
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
