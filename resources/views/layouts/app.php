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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) (config('app.name'))) ?></title>
    <style>
        :root { --fg:#101418; --muted:#5a6470; --accent:#1f6feb; --bg:#f5f7fa; --card:#fff; }
        * { box-sizing: border-box; }
        body { font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: var(--fg); background: var(--bg); margin: 0; }
        main { max-width: 760px; margin: 3rem auto; padding: 0 1.25rem; }
        .card { background: var(--card); border: 1px solid #e4e8ee; border-radius: 12px; padding: 1.5rem 1.75rem; margin-bottom: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
        h1 { margin-top: 0; }
        .muted { color: var(--muted); }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; }
        .pill.ok { background: #dcfce7; color: #166534; }
        .pill.warn { background: #fef9c3; color: #854d0e; }
        .pill.err { background: #fee2e2; color: #991b1b; }
        a { color: var(--accent); }
        code { background: #eef2f7; padding: 1px 6px; border-radius: 4px; }
        footer { color: var(--muted); font-size: 13px; margin-top: 2rem; text-align: center; }
        /* --- Announcement modal -------------------------------------- */
        .announce-backdrop {
            position: fixed; inset: 0;
            background: rgba(16, 20, 24, 0.65);
            display: flex; align-items: center; justify-content: center;
            padding: 1rem; z-index: 1000;
        }
        .announce-modal {
            background: var(--card); border-radius: 12px;
            max-width: 560px; width: 100%; padding: 1.75rem;
            box-shadow: 0 12px 48px rgba(0,0,0,.35);
            max-height: 80vh; overflow-y: auto;
        }
        .announce-modal h2 { margin-top: 0; }
        .announce-modal .body { white-space: pre-wrap; line-height: 1.55; }
        .announce-modal form { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .8rem; }
        .announce-modal .actions { display: flex; gap: .6rem; align-items: center; }
        .announce-modal .ok-btn {
            background: var(--accent); color: #fff; border: 0;
            padding: .55rem 1.4rem; border-radius: 6px; font: inherit; cursor: pointer;
        }
    </style>
</head>
<body>
<?php if ($__activeAnnouncement !== null): ?>
    <div class="announce-backdrop" role="dialog" aria-modal="true"
         aria-labelledby="announce-subject">
        <div class="announce-modal">
            <h2 id="announce-subject"><?= e((string) ($__activeAnnouncement['subject'] ?? '')) ?></h2>
            <div class="body"><?= e((string) ($__activeAnnouncement['body'] ?? '')) ?></div>
            <form method="post"
                  action="<?= e($__appPath) ?>/announcements/<?= (int) $__activeAnnouncement['id'] ?>/dismiss">
                <input type="hidden" name="_csrf" value="<?= e($__csrfForModal) ?>">
                <input type="hidden" name="redirect" value="<?= e($__currentPath) ?>">
                <label>
                    <input type="checkbox" name="suppress" value="1">
                    <strong>Don't show this again</strong>
                </label>
                <div class="actions">
                    <button type="submit" class="ok-btn">Okay</button>
                    <span class="muted" style="font-size:13px;">
                        Ticking the box hides this message for you permanently.
                        Leaving it unticked just dismisses for this session.
                    </span>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<main>
    <?= $slot ?>
    <footer>
        PayTracker <code><?= e(\PayTracker\Support\Version::string()) ?></code> &middot;
        env <code><?= e((string) (config('app.env'))) ?></code> &middot;
        php <code><?= e(PHP_VERSION) ?></code>
    </footer>
</main>
</body>
</html>
