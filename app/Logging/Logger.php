<?php

declare(strict_types=1);

namespace PayTracker\Logging;

/**
 * Minimal JSON-lines logger.
 *
 * Writes one structured event per line to `storage/logs/app.log` so the log
 * can be tail'd or shipped to any JSON-aware aggregator without a parser. We
 * deliberately do not pull in monolog yet — adding it later is trivial because
 * every call site goes through this class.
 */
final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'ts'      => gmdate('c'),
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES) . "\n";

        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
