<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Request;
use PayTracker\Http\Response;

/**
 * HomeController — preview landing page.
 *
 * On the feature/preview channel this page acts as a control surface for
 * stakeholders: it confirms the build is live, names the active environment,
 * and links to the QA test plan. It deliberately does NOT query production
 * data (the spec mandates production isolation during preview validation).
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('home', [
            'appName' => (string) config('app.name', 'PayTracker'),
            'env'     => (string) config('app.env', 'production'),
            'version' => $this->version(),
        ]);
    }

    /**
     * Read the marketing/version marker shipped at the repo root. Falls back
     * to `unknown` rather than throwing — the home page must render even if
     * the file is missing on a partial deploy.
     */
    private function version(): string
    {
        $path = base_path('version.json');
        if (! is_file($path)) {
            return 'unknown';
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return 'unknown';
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return 'unknown';
        }
        return isset($decoded['patch']) ? 'patch ' . (string) $decoded['patch'] : 'unknown';
    }
}
