<?php

declare(strict_types=1);

namespace PayTracker\Support;

/**
 * Version — user-facing semantic version + build id, surfaced in the
 * footer on every layout.
 *
 * Composition rules (matching what we display, e.g. `v2.2.5+abc1234`):
 *
 *   - `major.minor.patch` come from `version.json` at the repo root.
 *     All three are manually edited; bumping minor or major is a
 *     judgement call (breaking change, big feature, etc.) and patch
 *     is bumped as we ship fixes.
 *   - `build` is the first 7 chars of the git commit deployed at
 *     build time, read from `env('BUILD_COMMIT')`. The deploy
 *     workflow already writes the full SHA into the remote `.env`
 *     under that key; we just trim it. Falls back to the literal
 *     string `dev` when the env var is empty (local development,
 *     unit-test runs).
 *
 * Resolution is cached per-request so repeated footer renders (and
 * any controller that prints the version) don't re-hit disk.
 *
 * Failure surface: every path falls back to a safe default rather
 * than throwing. The footer is shown on EVERY page including
 * pre-auth surfaces; we never want a missing-file edge case to take
 * down the layout. If `version.json` disappears entirely, the
 * version reads `v0.0.0+dev` rather than blowing up.
 */
final class Version
{
    /** Default fallback build identifier when BUILD_COMMIT is empty. */
    private const FALLBACK_BUILD = 'dev';

    /** Length of the build id slice taken from the full SHA. */
    private const BUILD_ID_LEN = 7;

    /**
     * Cached resolved version per-request. Keyed by null because
     * there's a single version string for the running app.
     */
    private static ?string $cached = null;

    /**
     * Cached structured parts, in case callers want major/minor/
     * patch/build separately (e.g. /health.json).
     *
     * @var array{major:int,minor:int,patch:int,build:string}|null
     */
    private static ?array $cachedParts = null;

    /**
     * Full display string: `v{major}.{minor}.{patch}+{build}`.
     */
    public static function string(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $p = self::parts();
        self::$cached = sprintf('v%d.%d.%d+%s', $p['major'], $p['minor'], $p['patch'], $p['build']);
        return self::$cached;
    }

    /**
     * Structured parts (used by /health.json + tests).
     *
     * @return array{major:int,minor:int,patch:int,build:string}
     */
    public static function parts(): array
    {
        if (self::$cachedParts !== null) {
            return self::$cachedParts;
        }
        $json = self::readVersionJson();
        $major = isset($json['major']) && is_numeric($json['major']) ? (int) $json['major'] : 0;
        $minor = isset($json['minor']) && is_numeric($json['minor']) ? (int) $json['minor'] : 0;
        $patch = isset($json['patch']) && is_numeric($json['patch']) ? (int) $json['patch'] : 0;
        self::$cachedParts = [
            'major' => $major,
            'minor' => $minor,
            'patch' => $patch,
            'build' => self::resolveBuildId(),
        ];
        return self::$cachedParts;
    }

    /**
     * Reset the per-request cache. Used by the test suite when it
     * needs to verify resolution against a temporary `version.json`.
     */
    public static function reset(): void
    {
        self::$cached      = null;
        self::$cachedParts = null;
    }

    /**
     * Read and decode `version.json`. Returns an empty array on any
     * failure -- the caller's defaulting takes over from there.
     *
     * @return array<string,mixed>
     */
    private static function readVersionJson(): array
    {
        $path = function_exists('base_path') ? base_path('version.json') : (dirname(__DIR__, 2) . '/version.json');
        if (! is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Pick the build id from BUILD_COMMIT (set in .env by the deploy
     * workflow). We accept either a full 40-char SHA or anything
     * shorter; trim to BUILD_ID_LEN and lowercase to keep the footer
     * consistent. Anything non-hex falls through to the literal
     * `dev` fallback so we never embed surprise characters.
     */
    private static function resolveBuildId(): string
    {
        $raw = function_exists('env') ? env('BUILD_COMMIT', '') : ((string) (getenv('BUILD_COMMIT') ?: ''));
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return self::FALLBACK_BUILD;
        }
        if (preg_match('/^[A-Fa-f0-9]+$/', $raw) !== 1) {
            return self::FALLBACK_BUILD;
        }
        return strtolower(substr($raw, 0, self::BUILD_ID_LEN));
    }
}
