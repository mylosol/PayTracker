<?php

declare(strict_types=1);

namespace PayTracker\Http;

/**
 * HTTP Kernel — the single seam between the front controller and the rest of
 * the framework. Concentrating the request → dispatch → response chain here
 * means `public/index.php` stays tiny and free of business logic.
 */
final class Kernel
{
    public function __construct(private readonly Router $router)
    {
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }
}
