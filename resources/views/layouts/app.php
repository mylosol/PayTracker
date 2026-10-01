<?php
/** @var string $slot */
$__activeAnnouncement = active_announcement_for_modal();
$__csrfForModal       = $__activeAnnouncement !== null
    ? \PayTracker\Foundation\Application::instance()->make(\PayTracker\Security\Csrf::class)->token()
    : '';
// Path-only base for the dismiss form. APP_URL is the absolute URL
// for the current channel; we only need the path portion ("" on
// production, "/preview" on the preview channel) so the form posts
// back to the same origin / same channel without hard-coding.
$__appPath     = (string) (parse_url((string) (config('app.url') ?? ''), PHP_URL_PATH) ?? '');
$__appPath     = rtrim($__appPath, '/');
$__currentPath = $_SERVER['REQUEST_URI'] ?? ($__appPath . '/');

// Resolve the signed-in account so the nav can role-gate sections.
// We deliberately accept null here -- pre-auth pages (/, /login,
// /register) ship the same layout but with a slimmer nav.
$__authService = \PayTracker\Foundation\Application::instance()->make(\PayTracker\Auth\AuthService::class);
$__account     = $__authService->currentAccount();
$__role        = is_array($__account) ? (string) ($__account['role'] ?? '') : '';
$__isAdmin     = in_array($__role, ['admin', 'super_admin'], true);
$__isSuper     = $__role === 'super_admin';
$__darkMode    = is_array($__account) && (int) ($__account['dark_mode'] ?? 0) === 1;

// CSRF for the inline logout form in the nav.
$__csrfForLogout = is_array($__account)
    ? \PayTracker\Foundation\Application::instance()->make(\PayTracker\Security\Csrf::class)->token()
    : '';

// Resolve the compiled stylesheet path relative to the current
// channel. The build pipeline writes it to public/assets/app.css.
// The .htaccess static-asset passthrough is scoped to URLs that
// literally contain "/public/" so requests stay isolated from
// the legacy code tree -- hence the /public/assets/... shape here
// rather than a "cleaner" /assets/... URL.
//
// Each asset URL gets a `?v=<file mtime>` cache buster. Cloudflare's
// edge cache (max-age=604800) was happily serving a stale app.css
// for ~44h after the self-host-fonts deploy went out — old CSS
// still had @import url(fonts.googleapis...) at the top, so the
// browser kept fetching Google's fonts CSS even though our HTML no
// longer referenced it. Mtime changes on every rsync, so the URL
// changes every deploy and the edge treats it as a new asset.
$__publicAssetsDir = dirname(__DIR__, 3) . '/public/assets';
$__bust = static fn (string $diskPath): string =>
    ($m = @filemtime($diskPath)) ? '?v=' . $m : '';
$__cssUrl       = $__appPath . '/public/assets/app.css'             . $__bust($__publicAssetsDir . '/app.css');
$__logoUrl      = $__appPath . '/public/assets/logo-mark.svg'       . $__bust($__publicAssetsDir . '/logo-mark.svg');
// Preload the body weight only. Other Inter weights and JetBrains
// Mono can lazy-load via @font-face — they're either above the
// fold but used sparingly (semibold/bold headings render fine with
// system-font fallback for the ~50ms before the woff2 arrives) or
// below the fold entirely (mono <code> in audit/diagnostics).
$__fontPreload  = $__appPath . '/public/assets/fonts/inter-400.woff2' . $__bust($__publicAssetsDir . '/fonts/inter-400.woff2');

// Nav items. Each entry is [href, label, visible?]. The visible
// flag lets us role-gate at the data layer rather than scattering
// if-blocks through the markup, which makes it easy to spot when
// a new section is missing a gate.
$__nav = [
    ['href' => $__appPath . '/dashboard',         'label' => 'Dashboard',  'visible' => is_array($__account)],
    ['href' => $__appPath . '/loads/new',         'label' => 'Add load',   'visible' => is_array($__account)],
    ['href' => $__appPath . '/reconcile',         'label' => 'Reconcile',  'visible' => is_array($__account)],
    ['href' => $__appPath . '/locations',         'label' => 'Locations',  'visible' => $__isAdmin],
    ['href' => $__appPath . '/distances',         'label' => 'Distances',  'visible' => $__isAdmin],
    ['href' => $__appPath . '/pay-admin',         'label' => 'Pay admin',  'visible' => $__isAdmin],
    ['href' => $__appPath . '/admin',             'label' => 'Admin',      'visible' => $__isAdmin],
];
?>
<!doctype html>
<html lang="en"<?= $__darkMode ? ' class="dark"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1E293B">
    <title><?= e((string) (config('app.name'))) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e($__logoUrl) ?>">
    <link rel="apple-touch-icon" href="<?= e($__appPath) ?>/public/images/appicons/apple-touch-icon.png">
    <link rel="manifest" href="<?= e($__appPath) ?>/manifest.json">

    <!--
        Self-hosted fonts. The body weight (Inter 400) is preloaded
        so it's in flight in parallel with the HTML parse, arriving
        before paint. The remaining weights are declared in app.css
        via @font-face with `font-display: optional` and pulled
        from /public/assets/fonts/ on first reference.
    -->
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="<?= e($__fontPreload) ?>">

    <link rel="stylesheet" href="<?= e($__cssUrl) ?>">

    <?php /* Running build id, baked into the HTML on every render. The
             update-notifier JS reads this and compares it to a periodic
             GET of /version.json to spot a fresh deploy without reloading
             on its own. */ ?>
    <meta name="app-version" content="<?= e(\PayTracker\Support\Version::string()) ?>">
    <meta name="app-base-path" content="<?= e($__appPath) ?>">
</head>
<body class="min-h-screen flex flex-col">
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <header class="bg-brand-surface text-white sticky top-0 z-40 shadow-card-elev">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14 sm:h-16">
            <a href="<?= e($__appPath) ?>/" class="flex items-center gap-2.5 text-white hover:text-white no-underline group">
                <img src="<?= e($__logoUrl) ?>" alt="" class="h-8 w-8 sm:h-9 sm:w-9" width="32" height="32">
                <span class="font-bold text-lg sm:text-xl tracking-tight">PayTracker</span>
            </a>

            <?php if (is_array($__account)): ?>
                <!-- Desktop nav -->
                <nav class="hidden md:flex items-center gap-1" aria-label="Primary">
                    <?php foreach ($__nav as $item): ?>
                        <?php if (! $item['visible']) continue; ?>
                        <a href="<?= e($item['href']) ?>"
                           class="text-white/90 hover:text-white hover:bg-brand-surface-hover px-3 py-2 rounded-md text-sm font-medium no-underline transition-colors">
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                    <form method="post" action="<?= e($__appPath) ?>/logout" class="ml-2">
                        <input type="hidden" name="_csrf" value="<?= e($__csrfForLogout) ?>">
                        <button type="submit"
                                class="text-white/90 hover:text-white hover:bg-brand-surface-hover px-3 py-2 rounded-md text-sm font-medium transition-colors">
                            Sign out
                        </button>
                    </form>
                </nav>

                <!-- Mobile hamburger -->
                <button type="button"
                        class="md:hidden inline-flex items-center justify-center w-11 h-11 -mr-2 rounded-md text-white hover:bg-brand-surface-hover"
                        aria-controls="mobile-drawer"
                        aria-expanded="false"
                        aria-label="Open navigation"
                        data-drawer-toggle>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
            <?php else: ?>
                <nav class="flex items-center gap-2" aria-label="Primary">
                    <a href="<?= e($__appPath) ?>/login"
                       class="text-white/90 hover:text-white hover:bg-brand-surface-hover px-3 py-2 rounded-md text-sm font-medium no-underline transition-colors">
                        Sign in
                    </a>
                </nav>
            <?php endif; ?>
        </div>
    </header>

    <?php if (is_array($__account)): ?>
        <!-- Mobile drawer (off-canvas). Hidden by default; toggled via tiny JS at the bottom of the page. -->
        <div id="mobile-drawer"
             class="md:hidden fixed inset-0 z-50 hidden"
             role="dialog"
             aria-modal="true"
             aria-label="Mobile navigation"
             data-drawer>
            <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/70" data-drawer-backdrop></div>
            <nav class="absolute top-0 right-0 h-full w-4/5 max-w-xs bg-white dark:bg-slate-900 shadow-card-elev flex flex-col"
                 aria-label="Primary mobile">
                <div class="flex items-center justify-between px-4 h-14 border-b border-brand-line dark:border-slate-700">
                    <span class="font-bold text-brand-ink dark:text-slate-100">Menu</span>
                    <button type="button"
                            class="inline-flex items-center justify-center w-11 h-11 rounded-md text-brand-ink dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800"
                            aria-label="Close navigation"
                            data-drawer-close>
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto py-2">
                    <?php foreach ($__nav as $item): ?>
                        <?php if (! $item['visible']) continue; ?>
                        <a href="<?= e($item['href']) ?>"
                           class="flex items-center min-h-[56px] px-5 text-base font-medium text-brand-ink dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800 no-underline border-b border-brand-line/50 dark:border-slate-700/60">
                            <?= e($item['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <form method="post" action="<?= e($__appPath) ?>/logout" class="px-4 py-4 border-t border-brand-line dark:border-slate-700">
                    <input type="hidden" name="_csrf" value="<?= e($__csrfForLogout) ?>">
                    <button type="submit" class="btn-secondary w-full">Sign out</button>
                </form>
            </nav>
        </div>
    <?php endif; ?>

    <?php if ($__activeAnnouncement !== null): ?>
        <div class="fixed inset-0 z-50 bg-slate-900/60 flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="announce-subject">
            <div class="bg-white rounded-xl2 shadow-card-elev max-w-xl w-full p-6 sm:p-8 max-h-[85vh] overflow-y-auto">
                <h2 id="announce-subject" class="mt-0"><?= e((string) ($__activeAnnouncement['subject'] ?? '')) ?></h2>
                <div class="whitespace-pre-wrap text-brand-ink leading-relaxed mt-3">
                    <?= e((string) ($__activeAnnouncement['body'] ?? '')) ?>
                </div>
                <form method="post"
                      action="<?= e($__appPath) ?>/announcements/<?= (int) $__activeAnnouncement['id'] ?>/dismiss"
                      class="mt-6 flex flex-col gap-3">
                    <input type="hidden" name="_csrf" value="<?= e($__csrfForModal) ?>">
                    <input type="hidden" name="redirect" value="<?= e($__currentPath) ?>">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="suppress" value="1" class="field-checkbox">
                        <span><strong>Don't show this again</strong></span>
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="btn-primary">Okay</button>
                        <span class="text-sm text-brand-muted">
                            Ticking the box hides this message for you permanently.
                        </span>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <main id="main-content" class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
        <?= $slot ?>
    </main>

    <footer class="text-center text-xs text-brand-muted py-6 px-4 border-t border-brand-line bg-white dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700">
        PayTracker <code><?= e(\PayTracker\Support\Version::string()) ?></code>
        &middot; env <code><?= e((string) (config('app.env'))) ?></code>
        &middot; php <code><?= e(PHP_VERSION) ?></code>
    </footer>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker
                    .register('<?= e($__appPath) ?>/sw.js')
                    .catch(function () {});
            });
        }
    </script>

    <?php /* Update-available notifier. A tab left open across a deploy
             polls /version.json once a minute (plus on focus and
             tab-visible transitions) and shows a bottom-right toast
             when the deployed build differs from the one the HTML was
             rendered with. The user chooses when to reload; we never
             auto-navigate so a half-filled load-entry form doesn't
             get nuked.

             Reload button first unregisters every service-worker
             registration and deletes every Cache Storage key, then
             location.reload()s. PayTracker's SW only caches CSS and
             fonts/images (not HTML), but a stale cached CSS was the
             exact failure mode behind the mobile-drawer dark-mode
             fix shipping without reaching users — being defensive
             here means a Reload click is unambiguously effective.

             Build id of "dev" is a sentinel for local/CI runs where
             we don't have a real deployed version to compare against;
             the notifier stays dormant in those contexts.

             Shown toast is anchored bottom-right with role="status"
             and aria-live so it's announced for screen reader users
             without stealing focus from a form they're filling in. */ ?>
    <div id="update-available-toast"
         role="status"
         aria-live="polite"
         hidden
         class="fixed bottom-4 right-4 z-50 max-w-xs rounded-xl border border-brand-line bg-white dark:bg-slate-800 dark:border-slate-700 shadow-card-elev px-4 py-3 text-sm">
        <div class="flex items-start gap-3">
            <div class="flex-1">
                <p class="font-semibold text-brand-ink dark:text-slate-100 m-0">
                    New version available
                </p>
                <p class="text-brand-muted dark:text-slate-300 mt-1 mb-0 text-[13px]">
                    PayTracker updated while this tab was open.
                    Reload to pick up the latest build.
                </p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <button type="button"
                            id="update-available-reload"
                            class="btn-primary btn-sm">
                        Reload now
                    </button>
                    <button type="button"
                            id="update-available-dismiss"
                            class="btn-secondary btn-sm"
                            aria-label="Dismiss update notification">
                        Later
                    </button>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const metaVersion = document.querySelector('meta[name="app-version"]');
            const metaBase    = document.querySelector('meta[name="app-base-path"]');
            if (!metaVersion) return;

            const running = (metaVersion.content || '').trim();
            if (!running || /\+dev$/.test(running)) {
                // Local or CI build — the deployed version is nondeterministic
                // and comparing would nag forever. Stay dormant.
                return;
            }
            const basePath = metaBase ? (metaBase.content || '') : '';

            const toast        = document.getElementById('update-available-toast');
            const reloadBtn    = document.getElementById('update-available-reload');
            const dismissBtn   = document.getElementById('update-available-dismiss');
            if (!toast || !reloadBtn || !dismissBtn) return;

            const DISMISS_KEY  = 'paytracker.updateDismissed'; // sessionStorage
            const POLL_MS      = 60_000;

            function readDismissed() {
                try { return sessionStorage.getItem(DISMISS_KEY) || ''; }
                catch (e) { return ''; }
            }
            function writeDismissed(v) {
                try { sessionStorage.setItem(DISMISS_KEY, v); }
                catch (e) { /* fail silent */ }
            }

            function render(deployed) {
                // Remember the latest known deployed version on the DOM
                // node itself so the Dismiss handler can record exactly
                // what the user is dismissing. Keeps the two in lockstep
                // without a shared JS variable.
                toast.dataset.deployed = deployed || '';
                if (!deployed || deployed === running) { toast.hidden = true; return; }
                if (readDismissed() === deployed)      { toast.hidden = true; return; }
                toast.hidden = false;
            }

            async function check() {
                try {
                    const resp = await fetch(basePath + '/version.json', {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!resp.ok) return;
                    const data = await resp.json();
                    const deployed = (data && typeof data.version === 'string') ? data.version.trim() : '';
                    render(deployed);
                } catch (e) {
                    // Offline / network blip — do nothing; next tick will retry.
                }
            }

            // Reload: unregister SWs and purge caches best-effort, then
            // force a navigation. Any step that throws is swallowed —
            // the reload MUST happen regardless or the toast becomes a
            // no-op button, which is worse than caching bugs.
            async function forceReload() {
                reloadBtn.disabled    = true;
                reloadBtn.textContent = 'Reloading…';
                try {
                    if ('serviceWorker' in navigator) {
                        const regs = await navigator.serviceWorker.getRegistrations();
                        await Promise.all(regs.map(r => r.unregister().catch(() => {})));
                    }
                } catch (e) { /* fall through */ }
                try {
                    if (typeof caches !== 'undefined') {
                        const keys = await caches.keys();
                        await Promise.all(keys.map(k => caches.delete(k).catch(() => {})));
                    }
                } catch (e) { /* fall through */ }
                window.location.reload();
            }

            reloadBtn.addEventListener('click', () => { forceReload(); });
            dismissBtn.addEventListener('click', () => {
                // Store the version we just dismissed so the toast
                // doesn't nag in-session but DOES reappear when an even
                // newer build ships.
                const stamp = (toast.dataset.deployed || '').trim();
                if (stamp) writeDismissed(stamp);
                toast.hidden = true;
            });

            // Kick off: one immediate check so a user returning to an
            // already-stale tab is told right away, then a 60s poll.
            check();
            setInterval(check, POLL_MS);

            // Any time the user shows interest in the tab (focus,
            // visibility flip from hidden → visible), re-check. This is
            // what makes it feel prompt without hammering the server.
            window.addEventListener('focus', check);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') check();
            });
        })();
    </script>

    <?php if (is_array($__account)): ?>
        <script>
            // Mobile drawer toggle. Tiny vanilla JS — no framework
            // needed for an open/close interaction, and we want zero
            // runtime cost on desktop.
            (function () {
                var drawer  = document.getElementById('mobile-drawer');
                var toggle  = document.querySelector('[data-drawer-toggle]');
                var close   = document.querySelector('[data-drawer-close]');
                var backdrop= document.querySelector('[data-drawer-backdrop]');
                if (!drawer || !toggle) return;
                var open = function () {
                    drawer.classList.remove('hidden');
                    toggle.setAttribute('aria-expanded', 'true');
                    // Trap scroll on the underlying page while the
                    // drawer is open so iOS doesn't bleed scroll
                    // momentum into the body.
                    document.body.style.overflow = 'hidden';
                };
                var shut = function () {
                    drawer.classList.add('hidden');
                    toggle.setAttribute('aria-expanded', 'false');
                    document.body.style.overflow = '';
                };
                toggle.addEventListener('click', open);
                if (close)   close.addEventListener('click',   shut);
                if (backdrop) backdrop.addEventListener('click', shut);
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !drawer.classList.contains('hidden')) shut();
                });
            })();
        </script>
    <?php endif; ?>
</body>
</html>
