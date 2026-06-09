<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Auth\AuthService;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use PayTracker\Models\Account;
use PayTracker\Models\AuditLog;
use PayTracker\Models\City;
use PayTracker\Models\CityDistance;
use PayTracker\Security\Csrf;
use PayTracker\Security\Session;

/**
 * DistancesController — admin-managed view of the `city_distances`
 * cache that powers PayCalculator + the load form's mile lookups.
 *
 * Routes (admin+ gated):
 *   GET  /distances                  — paginated/searchable list, lookup
 *                                       form, summary counters
 *   POST /distances                  — set / change an admin override
 *                                       (`source = 'admin'` row upserted)
 *   POST /distances/delete           — delete a single (from, to, source)
 *                                       row from the cache
 *
 * The "admin source" mechanic is the cornerstone of editing:
 *   - Every admin "edit" inserts/updates a row with source='admin'.
 *   - 'admin' sorts ASCII-first among the source strings used
 *     elsewhere (`google_maps`, `largeMiles`, `pcola_largeMiles`),
 *     so CityDistance::lookupOrFetch naturally prefers it.
 *   - Deleting the admin row reverts the cache to whatever legacy
 *     or Google value was there before. Reversible by design.
 *   - Original legacy / Google rows are never overwritten by this
 *     surface; the only way to remove them is the explicit Delete
 *     action on that specific row.
 */
final class DistancesController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly CityDistance $distances,
        private readonly City $cities,
        private readonly AuditLog $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        if (($denied = $this->gate($request)) !== null) {
            return $denied;
        }

        $from   = trim((string) $request->input('from', ''));
        $to     = trim((string) $request->input('to', ''));
        $search = trim((string) $request->input('q',   ''));
        $page   = max(1, (int) (string) $request->input('page', '1'));

        $lookup = ($from !== '' && $to !== '')
            ? $this->distances->between($from, $to)
            : null;
        $list   = $this->distances->listForAdmin($search, $page, self::PER_PAGE);

        // Form prefill: when admin clicked "Override" on a specific
        // row the page comes back with edit_from / edit_to in the
        // querystring; we render the form pre-populated.
        $prefill = $this->resolvePrefill($request);

        return $this->view('distances/index', [
            'base'         => $request->basePath(),
            'csrfToken'    => $this->csrf->token(),
            'flash'        => $this->popFlash(),
            'summary'      => $this->distances->summary(),
            'cities'       => $this->cities->allForPicker(),
            'from'         => $from,
            'to'           => $to,
            'q'            => $search,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'rows'         => $list['rows'],
            'totalRows'    => $list['total'],
            'lookup'       => $lookup,
            'prefill'      => $prefill,
            'adminSource'  => CityDistance::SOURCE_ADMIN,
        ]);
    }

    /**
     * POST /distances — admin override upsert. Handles both "add a
     * new (from, to) distance" and "change an existing distance" —
     * structurally they're the same operation (the admin row gets
     * created on first save, updated thereafter).
     */
    public function store(Request $request): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $actor = $this->auth->currentAccount();

        $fromName = trim((string) $request->input('from_name', ''));
        $toName   = trim((string) $request->input('to_name',   ''));
        $milesRaw = trim((string) $request->input('miles',     ''));

        if ($fromName === '' || $toName === '') {
            return $this->failBack($request, 'Both From and To cities are required.');
        }
        if ($fromName === $toName) {
            return $this->failBack($request, 'From and To cannot be the same city.');
        }
        if (! ctype_digit($milesRaw) || (int) $milesRaw <= 0 || (int) $milesRaw > 9999) {
            return $this->failBack($request, 'Miles must be a positive integer between 1 and 9999.');
        }

        $miles  = (int) $milesRaw;
        $fromId = $this->cities->findOrCreate($fromName);
        $toId   = $this->cities->findOrCreate($toName);

        $inserted = $this->distances->upsertOverride($fromId, $toId, $miles);

        $this->audit->record(
            AuditLog::ACTION_DISTANCE_OVERRIDE_SET,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'from_city_id' => $fromId,
                'to_city_id'   => $toId,
                'from_name'    => $fromName,
                'to_name'      => $toName,
                'miles'        => $miles,
                'action'       => $inserted ? 'created' : 'updated',
            ],
        );

        $this->session->put('_flash', sprintf(
            '%s %s → %s = %d mi. Override active for pay + load lookups.',
            $inserted ? 'Set' : 'Updated',
            $fromName,
            $toName,
            $miles,
        ));
        return $this->redirect($request->basePath() . '/distances');
    }

    /**
     * POST /distances/delete — remove a single (from, to, source) row.
     */
    public function destroy(Request $request): Response
    {
        if (($denied = $this->gate($request, csrfCheck: true)) !== null) {
            return $denied;
        }
        $actor = $this->auth->currentAccount();

        $fromIdRaw = trim((string) $request->input('from_id', ''));
        $toIdRaw   = trim((string) $request->input('to_id',   ''));
        $source    = trim((string) $request->input('source',  ''));

        if (! ctype_digit($fromIdRaw) || ! ctype_digit($toIdRaw) || $source === '') {
            return $this->failBack($request, 'Could not identify the row to delete.');
        }
        $fromId = (int) $fromIdRaw;
        $toId   = (int) $toIdRaw;

        $existing = $this->distances->findExact($fromId, $toId, $source);
        if ($existing === null) {
            return $this->failBack($request, 'That row was already removed.');
        }

        if (! $this->distances->deleteRow($fromId, $toId, $source)) {
            return $this->failBack($request, 'Could not delete the row.');
        }

        $this->audit->record(
            AuditLog::ACTION_DISTANCE_ROW_DELETED,
            userId: (int) ($actor['id'] ?? 0),
            ipAddress: $this->clientIp(),
            metadata: [
                'from_city_id' => $fromId,
                'to_city_id'   => $toId,
                'from_name'    => $existing['from'],
                'to_name'      => $existing['to'],
                'source'       => $source,
                'miles'        => $existing['miles'],
            ],
        );

        $this->session->put('_flash', sprintf(
            'Deleted %s → %s (source=%s, %d mi).%s',
            $existing['from'],
            $existing['to'],
            $source,
            $existing['miles'],
            $source === CityDistance::SOURCE_ADMIN
                ? ' Cache reverts to the next-best source.'
                : '',
        ));
        return $this->redirect($request->basePath() . '/distances');
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
            return $this->failBack($request, 'Your session expired. Please try again.');
        }
        return null;
    }

    /**
     * When the admin clicks "Override" on a row, the index link
     * carries ?edit_from=X&edit_to=Y. Look up the current admin
     * override (if any) so the form prefills with the existing
     * miles instead of starting blank. Falls through to a fresh
     * blank prefill when no edit args are present.
     *
     * @return array{from:string, to:string, miles:string}|null
     */
    private function resolvePrefill(Request $request): ?array
    {
        $editFrom = trim((string) $request->input('edit_from', ''));
        $editTo   = trim((string) $request->input('edit_to',   ''));
        if ($editFrom === '' || $editTo === '') {
            return null;
        }
        // Pull the current admin miles if one exists, else fall
        // back to whatever the next-best source says (handy seed
        // for the form). between() returns rows in source-ASC
        // order so the first item is the winning row.
        $existing = $this->distances->between($editFrom, $editTo);
        $miles    = $existing !== [] ? (string) $existing[0]['miles'] : '';
        return [
            'from'  => $editFrom,
            'to'    => $editTo,
            'miles' => $miles,
        ];
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

    private function failBack(Request $request, string $message): Response
    {
        $this->session->put('_flash', $message);
        return $this->redirect($request->basePath() . '/distances');
    }

    private function popFlash(): ?string
    {
        $flash = $this->session->get('_flash');
        $this->session->forget('_flash');
        return is_string($flash) ? $flash : null;
    }
}
