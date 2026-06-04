<?php

declare(strict_types=1);

use PayTracker\Foundation\Application;
use PayTracker\Support\Config;
use PayTracker\View\View;

if (! function_exists('app')) {
    /**
     * Resolve the running Application instance, or a binding from its container.
     *
     * Centralising access here keeps the rest of the codebase free of global
     * singletons while still letting helpers reach the container ergonomically.
     */
    function app(?string $abstract = null): mixed
    {
        $app = Application::instance();
        return $abstract === null ? $app : $app->make($abstract);
    }
}

if (! function_exists('base_path')) {
    /**
     * Resolve an absolute path relative to the project root.
     */
    function base_path(string $relative = ''): string
    {
        $root = Application::instance()->basePath();
        return $relative === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

if (! function_exists('config')) {
    /**
     * Read a dot-notated configuration value, e.g. `config('database.host')`.
     */
    function config(string $key, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = Application::instance()->make(Config::class);
        return $config->get($key, $default);
    }
}

if (! function_exists('env')) {
    /**
     * Read a value from the environment, with type coercion for the common
     * truthy / falsy / null sentinels. Always prefer `config()` in app code so
     * that env access is centralised through `config/*.php`.
     */
    function env(string $key, mixed $default = null): mixed
    {
        // `getenv()` returns `string|false` and the superglobal lookups are
        // either set (any scalar) or unset (the `??` skips them) — so the
        // final value can never be literally `null`. We only need to bail
        // on the `false` (missing) and `''` (empty) sentinels.
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (! function_exists('e')) {
    /**
     * Escape a string for safe HTML output. Centralises our XSS defence so a
     * view file never needs to remember the htmlspecialchars argument list.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}

if (! function_exists('view')) {
    /**
     * Render a view template into an HTTP response body.
     */
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (! function_exists('layout')) {
    /**
     * Declare the layout template that should wrap the currently rendering
     * view. Templates call this near the top of the file:
     *     `<?php layout('layouts/app'); ?>`
     */
    function layout(string $template): void
    {
        View::setLayout($template);
    }
}

if (! function_exists('app_timezone')) {
    /**
     * The app's configured local timezone, defaulting to UTC when
     * APP_TIMEZONE isn't set. Centralises the lookup so callers
     * don't repeat the config('app.timezone', 'UTC') boilerplate.
     */
    function app_timezone(): \DateTimeZone
    {
        return new \DateTimeZone((string) (config('app.timezone', 'UTC') ?? 'UTC'));
    }
}

if (! function_exists('app_tz_abbrev')) {
    /**
     * Short timezone abbreviation for display in form hints + list
     * columns (e.g. "CST" / "CDT" / "UTC"). Computed against
     * "now" so the abbreviation tracks DST transitions automatically.
     */
    function app_tz_abbrev(): string
    {
        try {
            return (new \DateTime('now', app_timezone()))->format('T');
        } catch (\Throwable) {
            return 'UTC';
        }
    }
}

if (! function_exists('utc_to_local_for_input')) {
    /**
     * Convert a stored UTC DATETIME string into the value an
     * <input type="datetime-local"> expects (YYYY-MM-DDTHH:MM in
     * the app's local timezone). Empty / null input returns ''.
     *
     * The form input has no tzinfo, so the server-side normaliser
     * must read it as local time. utc_to_local_for_input is the
     * inverse of that normaliser -- it's how we pre-fill an edit
     * form with the same value the create form saw.
     */
    function utc_to_local_for_input(?string $utcDatetime): string
    {
        if (! is_string($utcDatetime) || $utcDatetime === '') {
            return '';
        }
        try {
            $utc   = new \DateTimeImmutable($utcDatetime, new \DateTimeZone('UTC'));
            $local = $utc->setTimezone(app_timezone());
            return $local->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            // Best-effort fallback: strip seconds, replace space with T.
            return str_replace(' ', 'T', substr($utcDatetime, 0, 16));
        }
    }
}

if (! function_exists('utc_to_local_display')) {
    /**
     * Pretty-print a stored UTC datetime as the admin's local time
     * with the timezone abbreviation appended. Used in list
     * columns ("Expires" / "Created") so an admin doesn't have to
     * translate UTC in their head.
     */
    function utc_to_local_display(?string $utcDatetime): string
    {
        if (! is_string($utcDatetime) || $utcDatetime === '') {
            return '—';
        }
        try {
            $utc   = new \DateTimeImmutable($utcDatetime, new \DateTimeZone('UTC'));
            $local = $utc->setTimezone(app_timezone());
            return $local->format('Y-m-d H:i') . ' ' . $local->format('T');
        } catch (\Throwable) {
            return $utcDatetime . ' UTC';
        }
    }
}

if (! function_exists('local_input_to_utc')) {
    /**
     * Inverse of utc_to_local_for_input(): take the value a
     * <input type="datetime-local"> POSTed (YYYY-MM-DDTHH:MM in
     * local time) and return the corresponding UTC DATETIME string
     * ready for MySQL.
     *
     * Also accepts YYYY-MM-DD HH:MM[:SS] for callers that round-trip
     * a stored value without re-parsing. Returns null when the input
     * is empty (= "no expiry"). Returns the input unchanged when
     * we can't parse it so the caller's downstream validation can
     * still produce a clear error.
     */
    function local_input_to_utc(string $localInput): ?string
    {
        $localInput = trim($localInput);
        if ($localInput === '') {
            return null;
        }
        $normalized = str_replace('T', ' ', $localInput);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized) === 1) {
            $normalized .= ':00';
        }
        try {
            $local = new \DateTimeImmutable($normalized, app_timezone());
            return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $localInput;
        }
    }
}

if (! function_exists('active_announcement_for_modal')) {
    /**
     * Layout-time helper used by `layouts/app.php` to decide whether
     * to render the announcement modal.
     *
     * Returns the active announcement row only when ALL of the
     * following hold:
     *   1. The user is signed in (currentAccount() returns non-null).
     *   2. The session flag `announcement_pending` is set
     *      (LoginController set it on successful sign-in; the
     *      dismiss endpoint clears it).
     *   3. There IS an active, non-template, non-expired
     *      announcement on file.
     *   4. The user hasn't permanently suppressed that specific
     *      announcement via a prior "don't show again" click.
     *
     * Returns null in every other case, so the layout renders no
     * modal markup at all (matching the spec's "show nothing if no
     * active announcement" requirement).
     *
     * Resolved via the global container so view templates don't
     * need to receive announcement data through every controller's
     * view() call -- it's a layout-level concern.
     *
     * @return array<string,mixed>|null
     */
    function active_announcement_for_modal(): ?array
    {
        try {
            $app = Application::instance();
            /** @var \PayTracker\Auth\AuthService $auth */
            $auth = $app->make(\PayTracker\Auth\AuthService::class);
            $account = $auth->currentAccount();
            if ($account === null) {
                return null;
            }
            /** @var \PayTracker\Security\Session $session */
            $session = $app->make(\PayTracker\Security\Session::class);
            $session->start();
            if (! $session->get('announcement_pending')) {
                return null;
            }
            /** @var \PayTracker\Models\Announcement $announcements */
            $announcements = $app->make(\PayTracker\Models\Announcement::class);
            $active = $announcements->currentActive();
            if ($active === null) {
                return null;
            }
            /** @var \PayTracker\Models\AnnouncementDismissal $dismissals */
            $dismissals = $app->make(\PayTracker\Models\AnnouncementDismissal::class);
            if ($dismissals->isSuppressed((int) $active['id'], (int) $account['id'])) {
                // User has permanently dismissed this one. Clear the
                // session flag too so we don't keep hitting these
                // queries on every page render this session.
                $session->forget('announcement_pending');
                return null;
            }
            return $active;
        } catch (\Throwable) {
            // Layout-time errors must NEVER crash a page render. If
            // the DB is unavailable or the schema migration hasn't
            // run yet, just render no modal.
            return null;
        }
    }
}
