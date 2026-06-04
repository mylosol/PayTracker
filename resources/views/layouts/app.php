<?php /** @var string $slot */ ?>
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
    </style>
</head>
<body>
<main>
    <?= $slot ?>
    <footer>
        <?= e((string) (config('app.name'))) ?> &middot;
        env <code><?= e((string) (config('app.env'))) ?></code> &middot;
        php <code><?= e(PHP_VERSION) ?></code>
    </footer>
</main>
</body>
</html>
