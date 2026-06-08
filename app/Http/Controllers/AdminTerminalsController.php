<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\City;
use PayTracker\Models\Terminal;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * AdminTerminalsController — Admin+ CRUD for the `terminals` table
 * (the user-facing "Begin Empty Locations" surface).
 *
 * Routes (admin+ gated):
 *   GET  /admin/terminals                 list every terminal
 *   GET  /admin/terminals/new             create form
 *   POST /admin/terminals                 create
 *   GET  /admin/terminals/{id}/edit       edit form
 *   POST /admin/terminals/{id}/edit       update
 *   POST /admin/terminals/{id}/deactivate soft-delete (active=0)
 *   POST /admin/terminals/{id}/reactivate undo soft-delete
 *
 * Why admin+ (not super_admin): adding / removing a terminal is a
 * routine operational tool — same tier as invite codes. The
 * cross-cutting changes that require Super Admin (role assignment,
 * announcements) aren't anything like as common as "we just opened
 * a new dispatch hub".
 *
 * No hard delete: deactivate flips `active = 0`. Historical loads
 * still reference the row by name even though the picker hides it.
 * Reactivation is a single-click admin recovery if the deactivation
 * was a mistake.
 *
 * Every mutate writes an audit row keyed on terminal_id + name so
 * "who renamed Niceville, FL?" stays answerable.
 */
final class AdminTerminalsController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly Terminal $terminals,
        private readonly City $cities,
        private readonly AuditLog $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        return $this->view('admin/terminals/index', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'rows'      => $this->terminals->listForAdmin(),
            'flash'     => $this->popFlash(),
        ]);
    }

    public function create(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }
        return $this->view('admin/terminals/new', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'cities'    => $this->cities->allForPicker(),
            'flash'     => $this->popFlash(),
            'old'       => [
                'name'    => (string) ($this->session->get('_old_term_name')    ?? ''),
                'city_id' => (string) ($this->session->get('_old_term_city_id') ?? ''),
            ],
        ]);
    }

    public function store(Request $request): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $actor = $this->auth->currentAccount();

        $name      = trim((string) $request->input('name', ''));
        $cityIdRaw = trim((string) $request->input('city_id', ''));

        $this->session->put('_old_term_name',    $name);
        $this->session->put('_old_term_city_id', $cityIdRaw);

        if (($err = $this->validateBasics($name)) !== null) {
            return $this->failBack($request->basePath() . '/admin/terminals/new', $err);
        }
        $cityId = $this->resolveCityId($cityIdRaw);

        $newId = $this->terminals->create($name, $cityId);
        if ($newId === null) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals/new',
                sprintf('A terminal named "%s" already exists. Reactivate it from the list if it was previously deactivated.', $name)
            );
        }

        $this->audit->record(
            AuditLog::ACTION_TERMINAL_CREATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'terminal_id' => $newId,
                'name'        => $name,
                'city_id'     => $cityId,
            ],
        );

        foreach (['_old_term_name', '_old_term_city_id'] as $k) {
            $this->session->forget($k);
        }
        $this->session->put('_flash', sprintf('Added Begin Empty location "%s".', $name));
        return $this->redirect($request->basePath() . '/admin/terminals');
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
        return $this->view('admin/terminals/edit', [
            'base'      => $request->basePath(),
            'csrfToken' => $this->csrf->token(),
            'row'       => $row,
            'cities'    => $this->cities->allForPicker(),
            'flash'     => $this->popFlash(),
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

        $name      = trim((string) $request->input('name', ''));
        $cityIdRaw = trim((string) $request->input('city_id', ''));

        if (($err = $this->validateBasics($name)) !== null) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals/' . (int) $row['id'] . '/edit',
                $err
            );
        }
        $cityId = $this->resolveCityId($cityIdRaw);

        $ok = $this->terminals->update((int) $row['id'], $name, $cityId);
        if (! $ok) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals/' . (int) $row['id'] . '/edit',
                'Could not save — the new name may collide with another terminal.'
            );
        }

        $this->audit->record(
            AuditLog::ACTION_TERMINAL_UPDATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'terminal_id'   => (int) $row['id'],
                'previous_name' => (string) $row['name'],
                'name'          => $name,
                'city_id'       => $cityId,
            ],
        );

        $this->session->put('_flash', sprintf('Updated Begin Empty location "%s".', $name));
        return $this->redirect($request->basePath() . '/admin/terminals');
    }

    public function deactivate(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        if (! $this->terminals->deactivate((int) $row['id'])) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals',
                sprintf('Could not deactivate "%s" — it may already be inactive.', (string) $row['name'])
            );
        }

        $this->audit->record(
            AuditLog::ACTION_TERMINAL_DEACTIVATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'terminal_id' => (int) $row['id'],
                'name'        => (string) $row['name'],
            ],
        );

        $this->session->put('_flash', sprintf('Deactivated "%s" — no longer shows in the driver picker.', (string) $row['name']));
        return $this->redirect($request->basePath() . '/admin/terminals');
    }

    public function reactivate(Request $request, string $id): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $row = $this->loadOrRedirect($request, $id);
        if ($row instanceof Response) {
            return $row;
        }
        $actor = $this->auth->currentAccount();

        if (! $this->terminals->reactivate((int) $row['id'])) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals',
                sprintf('Could not reactivate "%s" — it may already be active.', (string) $row['name'])
            );
        }

        $this->audit->record(
            AuditLog::ACTION_TERMINAL_REACTIVATED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'terminal_id' => (int) $row['id'],
                'name'        => (string) $row['name'],
            ],
        );

        $this->session->put('_flash', sprintf('Reactivated "%s".', (string) $row['name']));
        return $this->redirect($request->basePath() . '/admin/terminals');
    }

    // ====================================================================
    // Internal helpers
    // ====================================================================

    private function gate(Request $request, bool $csrfCheck = false): ?Response
    {
        $account = $this->auth->currentAccount();
        if ($account === null) {
            return $this->redirect($request->basePath() . '/login');
        }
        if (($denied = $this->requireRole($request, $account, Account::ROLE_ADMIN)) !== null) {
            return $denied;
        }
        $this->session->start();
        if ($csrfCheck && ! $this->csrf->verify($request->input('_csrf'))) {
            return $this->failBack($request->basePath() . '/admin/terminals', 'Your session expired. Please try again.');
        }
        return null;
    }

    /** @return array<string,mixed>|Response */
    private function loadOrRedirect(Request $request, string $idRaw): array|Response
    {
        if (! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            return $this->failBack($request->basePath() . '/admin/terminals', 'Invalid terminal id.');
        }
        $row = $this->terminals->findById((int) $idRaw);
        if ($row === null) {
            return $this->failBack(
                $request->basePath() . '/admin/terminals',
                sprintf('No terminal with id %d.', (int) $idRaw)
            );
        }
        return $row;
    }

    /**
     * Shared validation between create + update.
     */
    private function validateBasics(string $name): ?string
    {
        if ($name === '') {
            return 'Name is required.';
        }
        if (strlen($name) > Terminal::NAME_MAX_LEN) {
            return sprintf('Name is too long (max %d chars).', Terminal::NAME_MAX_LEN);
        }
        // City-shape sanity. Keeps casual typos out and matches the
        // surface area the city picker hands us.
        if (preg_match('/^[A-Za-z0-9\.\,\'\-\s]+$/', $name) !== 1) {
            return 'Name contains characters we don\'t allow. Stick to letters, digits, spaces, commas, periods, apostrophes, and hyphens.';
        }
        return null;
    }

    /**
     * Coerce the city_id form value. Empty / "0" / non-numeric all
     * resolve to null (no FK linkage). A positive integer is taken
     * at face value — the FK isn't enforced at the DB level so a
     * stale city_id from a deleted row degrades to "no distance
     * lookup" rather than blocking the save.
     */
    private function resolveCityId(string $raw): ?int
    {
        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
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
}
