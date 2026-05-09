<?php

declare(strict_types=1);

/**
 * Web routes.
 * Define your application's HTTP routes here.
 * Routes are loaded by the Application bootstrap process.
 */

use FlexPHP\Http\Router;

/** @var Router $router */

// Welcome page
$router->get('/', 'App\Controllers\WelcomeController@index', 'home');

// Example route group with prefix
$router->group('/api', function (Router $router) {
    $router->get('/users', 'App\Controllers\UserController@index', 'api.users.index');
    $router->post('/users', 'App\Controllers\UserController@store', 'api.users.store');
    $router->get('/users/{id}', 'App\Controllers\UserController@show', 'api.users.show');
    $router->put('/users/{id}', 'App\Controllers\UserController@update', 'api.users.update');
    $router->delete('/users/{id}', 'App\Controllers\UserController@destroy', 'api.users.destroy');
});
