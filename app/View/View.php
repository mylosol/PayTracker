<?php

declare(strict_types=1);

namespace PayTracker\View;

use RuntimeException;

/**
 * Plain-PHP view renderer.
 *
 * Templates live under `resources/views/` and may declare a layout by
 * calling the global `layout()` helper (registered below). The captured
 * child output is then exposed to the layout as `$slot`.
 *
 * Design notes:
 *   - We deliberately do not introduce a templating engine. PHP itself is a
 *     perfectly capable templating language, and the `e()` helper handles
 *     escaping. A compile/cache step would be a net loss on shared hosting.
 *   - Layout state is held in a private static stack rather than passed by
 *     reference through `extract()`. The stack handles nested renders
 *     correctly — only the innermost template's layout is honoured for that
 *     render frame.
 */
final class View
{
    /** @var list<?string> */
    private static array $layoutStack = [];

    public static function render(string $template, array $data = []): string
    {
        $path = self::resolve($template);

        self::$layoutStack[] = null;
        $slot   = self::evaluate($path, $data);
        $layout = array_pop(self::$layoutStack);

        if ($layout !== null) {
            return self::evaluate(self::resolve($layout), $data + ['slot' => $slot]);
        }
        return $slot;
    }

    /**
     * Called by a template (via the global `layout()` helper) to declare
     * which layout file should wrap its output.
     */
    public static function setLayout(string $template): void
    {
        if (self::$layoutStack === []) {
            // Called outside a render frame — ignore rather than throw so a
            // stray helper call cannot crash the page.
            return;
        }
        self::$layoutStack[array_key_last(self::$layoutStack)] = $template;
    }

    private static function evaluate(string $path, array $data): string
    {
        $renderer = static function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            /** @psalm-suppress UnresolvableInclude */
            require $__path;
            return (string) ob_get_clean();
        };
        return $renderer($path, $data);
    }

    private static function resolve(string $template): string
    {
        $path = base_path('resources/views/' . ltrim($template, '/') . '.php');
        if (! is_file($path)) {
            throw new RuntimeException(sprintf('View template not found: %s', $template));
        }
        return $path;
    }
}
