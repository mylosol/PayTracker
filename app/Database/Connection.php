<?php

declare(strict_types=1);

namespace PayTracker\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Lazy PDO connection factory.
 *
 * The connection is opened on first call to `pdo()` and then memoised. We
 * intentionally hand back a raw PDO handle rather than wrapping it — the rest
 * of the codebase already uses prepared statements via the base Model, and
 * a thin wrapper would add an abstraction layer without buying anything.
 *
 * Defensive defaults:
 *   - `ERRMODE_EXCEPTION` so silent failures become loud bugs.
 *   - `FETCH_ASSOC` because we never want positional rows in business logic.
 *   - `EMULATE_PREPARES=false` so integers stay integers and the server-side
 *     parameter binding is real, not string interpolation in the driver.
 *   - `STRINGIFY_FETCHES=false` so DECIMAL/INT columns keep their PHP type.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /**
     * @param array{
     *   host: string,
     *   port: int|string,
     *   database: string,
     *   username: string,
     *   password: string,
     *   charset: string,
     * } $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            (int) $this->config['port'],
            $this->config['database'],
            $this->config['charset'],
        );

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                ],
            );
        } catch (PDOException $e) {
            // Avoid leaking the DSN or credentials in the rethrown message.
            throw new RuntimeException('Database connection failed.', previous: $e);
        }

        return $this->pdo;
    }
}
