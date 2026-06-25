<?php
declare(strict_types=1);

/**
 * Version
 *
 * Resolves the application's display version string at runtime using the
 * artifact produced by the CI/CD pipeline (see `.github/workflows/deploy.yml`).
 *
 * Format: `YY.Patch.Increment+SHA` (e.g. `26.5.21+a1b2c3d`).
 *
 * - `YY`     two-digit year captured at build time.
 * - `Patch`  manually bumped integer sourced from the root `version.json`.
 * - `Increment` automated build counter — git revision count at build time.
 * - `SHA`    7-character short commit hash of the deployed commit.
 *
 * The build step writes a `.version` plain-text file into the deploy artifact;
 * this class reads that file. If the file is missing, unreadable, or malformed
 * (which can happen during a partial/aborted deploy, local development, or a
 * direct FTP upload that bypassed the pipeline), the class falls back through
 * progressively safer sources so the footer never crashes the page render:
 *
 *   1. `.version` file produced by CI
 *   2. `version.json` + best-effort runtime metadata (year + "dev")
 *   3. A hard-coded sentinel "dev" string
 *
 * Designed to be safe on shared hosting (DreamHost) and PHP 8.3.
 */
final class Version
{
    /** Cached resolved version string for the request lifecycle. */
    private static ?string $cached = null;

    /**
     * Get the application version string, resolving once per request.
     *
     * @return string Fully assembled version token, e.g. `26.5.21+a1b2c3d`.
     */
    public static function string(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        self::$cached = self::resolve();
        return self::$cached;
    }

    /**
     * Render the version string HTML-escaped for safe inline display.
     *
     * @return string HTML-escaped version token.
     */
    public static function html(): string
    {
        return htmlspecialchars(self::string(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Resolve the version through the documented fallback chain.
     *
     * @return string Best-effort version string. Always non-empty.
     */
    private static function resolve(): string
    {
        $compiled = self::readCompiledFile();
        if ($compiled !== null) {
            return $compiled;
        }

        $patch = self::readPatchFromJson();
        $year  = substr((string) date('Y'), -2);

        if ($patch !== null) {
            return sprintf('%s.%d.0+dev', $year, $patch);
        }

        return sprintf('%s.0.0+dev', $year);
    }

    /**
     * Read the CI-produced `.version` file from the repository root.
     *
     * The file is expected to contain a single line matching the canonical
     * version format. Whitespace is trimmed. Anything that does not match the
     * expected shape is rejected so a corrupted artifact never reaches the UI.
     *
     * @return string|null Trimmed version string, or null if unavailable / invalid.
     */
    private static function readCompiledFile(): ?string
    {
        $path = self::compiledFilePath();
        if ($path === null || !is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        // Accept YY.Patch.Increment+SHA — guard against accidental HTML/log bleed.
        if (!preg_match('/^\d{2}\.\d+\.\d+\+[A-Za-z0-9]{4,40}$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Read the manually maintained patch integer from `version.json`.
     *
     * @return int|null Patch number, or null if the file is missing/malformed.
     */
    private static function readPatchFromJson(): ?int
    {
        $root = self::repoRoot();
        if ($root === null) {
            return null;
        }

        $jsonPath = $root . DIRECTORY_SEPARATOR . 'version.json';
        if (!is_readable($jsonPath)) {
            return null;
        }

        $raw = @file_get_contents($jsonPath);
        if ($raw === false) {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded) || !isset($decoded['patch']) || !is_numeric($decoded['patch'])) {
            return null;
        }

        return (int) $decoded['patch'];
    }

    /**
     * Absolute path to the compiled `.version` file produced by CI.
     *
     * @return string|null Filesystem path, or null if the root cannot be located.
     */
    private static function compiledFilePath(): ?string
    {
        $root = self::repoRoot();
        return $root === null ? null : $root . DIRECTORY_SEPARATOR . '.version';
    }

    /**
     * Locate the repository / deployment root by walking up from this file.
     *
     * `Version.php` lives at `<root>/include/Version.php`, so the parent of
     * the parent of this file is the application root in both local
     * development and on the DreamHost deployment target.
     *
     * @return string|null Absolute filesystem path, or null on resolution failure.
     */
    private static function repoRoot(): ?string
    {
        $root = dirname(__DIR__);
        return is_dir($root) ? $root : null;
    }
}
