<?php

declare(strict_types=1);

namespace PayTracker\Http\Controllers;

use PayTracker\Http\Request;
use PayTracker\Http\Response;

/**
 * StaticPagesController — the housekeeping surfaces that don't touch the
 * database: tutorial, FAQ, About, Contact. Each action just renders a
 * view; no inputs, no writes, no auth requirement (these are public).
 *
 * Kept under one controller so a future "Help center" expansion (search,
 * cross-page nav, related-page suggestions) has a natural home without
 * needing to refactor four singleton controllers.
 *
 * Anonymous-friendly: a brand-new driver who hasn't been seeded an
 * account yet can still read the tutorial and FAQ. The /contact page
 * in particular needs to work signed-out for the "how do I get an
 * account" use case.
 */
final class StaticPagesController extends Controller
{
    public function tutorial(Request $request): Response
    {
        return $this->view('static/tutorial', [
            'base' => $request->basePath(),
        ]);
    }

    public function faq(Request $request): Response
    {
        return $this->view('static/faq', [
            'base' => $request->basePath(),
        ]);
    }

    public function about(Request $request): Response
    {
        return $this->view('static/about', [
            'base' => $request->basePath(),
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view('static/contact', [
            'base' => $request->basePath(),
        ]);
    }
}
