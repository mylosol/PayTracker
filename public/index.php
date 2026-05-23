<?php

declare(strict_types=1);

/*
 * Front controller — the single entry point for the modernised PayTracker.
 *
 * Apache rewrites every non-asset request (see .htaccess) to this file. The
 * file's only job is to (1) bootstrap the Application container, (2) build
 * an HTTP Request from globals, and (3) hand the Kernel a chance to
 * produce a Response. Anything richer belongs in `bootstrap/app.php` or the
 * router/controllers — not here.
 */

use PayTracker\Http\Kernel;
use PayTracker\Http\Request;

/** @var \PayTracker\Foundation\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel  = $app->make(Kernel::class);
$request = Request::fromGlobals();

$kernel->handle($request)->send();
