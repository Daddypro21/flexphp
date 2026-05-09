<?php

declare(strict_types=1);

/**
 * Web routes — file-based definitions.
 *
 * You can also declare routes directly on controller methods using PHP 8 attributes:
 *
 *   #[Get('/articles', name: 'articles.index')]
 *   public function index(Request $request): Response { ... }
 *
 * The RouteScanner automatically discovers all attribute-based routes in app/Controllers/.
 * Routes declared here take precedence over attribute routes on name conflicts.
 */

use FlexPHP\Http\Router;

/** @var Router $router */


$router->get('/hello', 'App\Controllers\HelloController@index', 'hello');
