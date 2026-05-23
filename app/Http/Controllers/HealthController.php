<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Database\Connection;
use PayTracker\Http\Request;
use PayTracker\Http\Response;
use Throwable;

/**
 * Health probe.
 *
 * Two surfaces:
 *   - `GET /health`       → human-readable HTML for stakeholders/QA
 *   - `GET /health.json`  → machine-readable JSON for the CI smoke test
 *
 * The endpoint reports:
 *   - PHP runtime version (proves the PHP 8.3 invariant from the spec),
 *   - Application environment (preview vs production),
 *   - Database round-trip status (without leaking credentials or schema).
 */
final class HealthController extends Controller
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function index(Request $request): Response
    {
        return $this->view('health', $this->collect());
    }

    public function json(Request $request): Response
    {
        $payload = $this->collect();
        $status  = $payload['database']['ok'] === true ? 200 : 503;
        return $this->json($payload, $status);
    }

    /**
     * Gather the health snapshot. Any DB exception is caught and reported as
     * a structured field — the endpoint itself must always answer, even when
     * the database is unreachable.
     *
     * @return array{php: string, env: string, time: string, database: array{ok: bool, error?: string}}
     */
    private function collect(): array
    {
        $database = ['ok' => false];
        try {
            $this->connection->pdo()->query('SELECT 1');
            $database['ok'] = true;
        } catch (Throwable $e) {
            $database['error'] = $e->getMessage();
        }

        return [
            'php'      => PHP_VERSION,
            'env'      => (string) config('app.env', 'production'),
            'time'     => gmdate('c'),
            'database' => $database,
        ];
    }
}
