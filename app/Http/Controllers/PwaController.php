<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Request;
use PayTracker\Http\Response;

final class PwaController extends Controller
{
    public function serviceWorker(Request $request): Response
    {
        $js = <<<'JS'
const CACHE = 'paytracker-v2';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(
                keys.filter(k => k !== CACHE).map(k => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    const isStatic = /\.(css|woff2?|ttf|svg|png|jpg|jpeg|gif|ico|webp)(\?|$)/.test(url.pathname);
    if (!isStatic) return;

    event.respondWith(
        caches.open(CACHE).then(cache =>
            cache.match(request, { ignoreSearch: true }).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (response.ok) cache.put(request, response.clone());
                    return response;
                });
            })
        )
    );
});
JS;

        return new Response($js, 200, [
            'Content-Type'  => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function manifest(Request $request): Response
    {
        $appPath = rtrim(
            (string) (parse_url((string) (config('app.url') ?? ''), PHP_URL_PATH) ?? ''),
            '/'
        );
        $icons   = $appPath . '/public/images/appicons';

        $data = [
            'name'             => (string) (config('app.name') ?? 'PayTracker'),
            'short_name'       => 'PayTracker',
            'description'      => 'Track your pay for each load',
            'start_url'        => $appPath . '/',
            'scope'            => $appPath . '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#1E293B',
            'theme_color'      => '#1E293B',
            'icons'            => [
                ['src' => "$icons/android-chrome-36x36.png",  'sizes' => '36x36',   'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-48x48.png",  'sizes' => '48x48',   'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-72x72.png",  'sizes' => '72x72',   'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-96x96.png",  'sizes' => '96x96',   'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-144x144.png", 'sizes' => '144x144', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "$icons/android-chrome-192x192.png", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-256x256.png", 'sizes' => '256x256', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-384x384.png", 'sizes' => '384x384', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => "$icons/android-chrome-512x512.png", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts'        => [
                [
                    'name'        => 'Dashboard',
                    'short_name'  => 'Dashboard',
                    'description' => 'My pay dashboard',
                    'url'         => $appPath . '/dashboard',
                    'icons'       => [['src' => "$icons/white-192x192.png", 'sizes' => '192x192']],
                ],
                [
                    'name'        => 'Add Load',
                    'short_name'  => 'Add Load',
                    'description' => 'Enter a new load',
                    'url'         => $appPath . '/loads/new',
                    'icons'       => [['src' => "$icons/white-192x192.png", 'sizes' => '192x192']],
                ],
                [
                    'name'        => 'Reconcile',
                    'short_name'  => 'Reconcile',
                    'description' => 'Reconcile pay',
                    'url'         => $appPath . '/reconcile',
                    'icons'       => [['src' => "$icons/white-192x192.png", 'sizes' => '192x192']],
                ],
            ],
        ];

        return new Response(
            body:    (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            status:  200,
            headers: ['Content-Type' => 'application/manifest+json; charset=utf-8'],
        );
    }
}
