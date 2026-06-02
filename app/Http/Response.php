<?php

declare(strict_types=1);

namespace PayTracker\Http;

/**
 * Immutable HTTP response value object.
 *
 * Concrete sending happens in `send()` so controllers stay pure and can be
 * unit-tested by inspecting the returned Response instance.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Encode a JSON payload with safe defaults. We refuse to silently drop
     * non-encodable values — `JSON_THROW_ON_ERROR` surfaces them as errors
     * instead of writing a corrupt response.
     */
    public static function json(mixed $payload, int $status = 200): self
    {
        return new self(
            body: (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self(body: '', status: $status, headers: ['Location' => $location]);
    }

    public function send(): void
    {
        if (! headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
    }
}
