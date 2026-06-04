<?php

declare(strict_types=1);

namespace PayTracker\Http;

use Closure;
use PayTracker\Foundation\Application;
use RuntimeException;

/**
 * Tiny URL router.
 *
 * Routes are registered with a method, a path pattern, and either a closure
 * or an `[Controller::class, 'method']` tuple. Path patterns support `{name}`
 * placeholders that map to controller method arguments.
 *
 * The router is intentionally minimal — no middleware pipeline, no route
 * groups, no caching. Those layers can be added once we have a feature set
 * that demands them.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, regex: string, handler: Closure|array{0:class-string,1:string}, params: list<string>}> */
    private array $routes = [];

    /** @param Closure|array{0:class-string,1:string} $handler */
    public function get(string $pattern, Closure|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param Closure|array{0:class-string,1:string} $handler */
    public function post(string $pattern, Closure|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** @param Closure|array{0:class-string,1:string} $handler */
    private function add(string $method, string $pattern, Closure|array $handler): void
    {
        $params = [];
        $regex  = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static function (array $m) use (&$params): string {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);

        $this->routes[] = [
            'method'  => $method,
            'pattern' => $pattern,
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
            'params'  => $params,
        ];
    }

    /**
     * Dispatch a request. Returns the controller's Response, or a 404 Response
     * when no route matches. We keep the 404 simple here; richer error pages
     * are rendered from views when the rest of the app is up.
     */
    public function dispatch(Request $request): Response
    {
        // Per RFC 9110, HEAD MUST behave identically to GET except for the
        // absence of a response body. We satisfy that by matching HEAD
        // against GET routes and letting the web server / PHP runtime
        // strip the body. The alternative (404 on HEAD) would break
        // curl -I, monitoring probes, and any HTTP cache validator.
        $matchMethod = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $matchMethod) {
                continue;
            }
            if (preg_match($route['regex'], $request->path, $matches) !== 1) {
                continue;
            }
            array_shift($matches);
            return $this->invoke($route['handler'], $request, array_combine($route['params'], $matches) ?: []);
        }
        return Response::html(view('errors/404'), 404);
    }

    /**
     * @param Closure|array{0:class-string,1:string} $handler
     * @param array<string, string> $params
     */
    private function invoke(Closure|array $handler, Request $request, array $params): Response
    {
        if ($handler instanceof Closure) {
            $result = $handler($request, ...array_values($params));
        } else {
            [$class, $method] = $handler;
            $controller       = Application::instance()->make($class);
            if (! is_object($controller) || ! method_exists($controller, $method)) {
                throw new RuntimeException(sprintf('Route handler %s::%s is not callable.', $class, $method));
            }
            $result = $controller->$method($request, ...array_values($params));
        }
        return $result instanceof Response ? $result : Response::html((string) $result);
    }
}
