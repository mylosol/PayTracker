<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Response;

/**
 * Base controller. Concrete controllers extend this class purely so that
 * shared helpers (view, json, redirect) can grow here without churning every
 * controller signature.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(view($template, $data), $status);
    }

    protected function json(mixed $payload, int $status = 200): Response
    {
        return Response::json($payload, $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }
}
