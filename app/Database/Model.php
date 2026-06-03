<?php

declare(strict_types=1);

namespace PayTracker\Database;

use PDO;
use PDOStatement;

/**
 * Lightweight base Model.
 *
 * Concrete models declare `protected static string $table` and use the
 * helpers below for parameter-bound CRUD. No string interpolation of user
 * input into SQL is permitted anywhere in the app — every query that takes
 * user data must go through `prepared()` and bind parameters explicitly.
 *
 * The helper deliberately does not synthesise WHERE clauses from associative
 * arrays — that pattern is what historically allowed SQL-injection slips. A
 * developer who needs a dynamic query should write the SQL inline and bind
 * named parameters by hand.
 */
abstract class Model
{
    /** Override in each subclass. */
    protected static string $table = '';

    public function __construct(protected readonly Connection $connection)
    {
    }

    /**
     * Execute a prepared statement against the live connection.
     *
     * `$bindings` accepts BOTH named (string-keyed) parameters AND positional
     * (int-keyed) parameters — PDO's native preparation mode requires
     * positional binding when a parameter would otherwise need to repeat
     * the same name in a query (MySQL's native prepared statements don't
     * support repeated named placeholders). The widened union type
     * (`array<int|string, ...>`) reflects that — callers may pass either
     * form depending on the query shape.
     *
     * @param array<int|string, scalar|null> $bindings
     */
    protected function prepared(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->connection->pdo()->prepare($sql);
        foreach ($bindings as $param => $value) {
            $stmt->bindValue(
                is_int($param) ? $param + 1 : ':' . ltrim($param, ':'),
                $value,
                match (true) {
                    is_int($value)  => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                },
            );
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * Quote a SQL identifier (table or column name). Identifier values are
     * never user-supplied in this codebase — the helper exists so subclasses
     * can interpolate their declared table name without lint warnings.
     */
    protected static function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
