<?php

declare(strict_types=1);

namespace Tests\Unit;

use FlexPHP\Http\Router;
use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for the FlexPHP Router.
 *
 * Covers: GET / POST route registration, named route URL generation,
 * 404 handling for unknown paths, and route group prefixes.
 */
class RouterTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a fresh Router instance for each test.
     */
    private function makeRouter(): Router
    {
        return new Router();
    }

    /**
     * Builds a minimal PSR-7-compatible Request stub for dispatch testing.
     *
     * @param string $method HTTP method (GET, POST, …).
     * @param string $uri    Request URI path.
     */
    private function makeRequest(string $method, string $uri): Request
    {
        return Request::create($method, $uri);
    }

    // -------------------------------------------------------------------------
    // GET route
    // -------------------------------------------------------------------------

    #[Test]
    public function getRouteIsRegisteredAndDispatched(): void
    {
        $router = $this->makeRouter();

        $router->get('/hello', fn(Request $req) => new Response('Hello, World!', 200));

        $response = $router->dispatch($this->makeRequest('GET', '/hello'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello, World!', (string) $response->getBody());
    }

    #[Test]
    public function getRoutePassesUriParametersToHandler(): void
    {
        $router = $this->makeRouter();

        $router->get('/users/{id}', function (Request $req, int $id) {
            return new Response("User {$id}", 200);
        });

        $response = $router->dispatch($this->makeRequest('GET', '/users/42'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('42', (string) $response->getBody());
    }

    // -------------------------------------------------------------------------
    // POST route
    // -------------------------------------------------------------------------

    #[Test]
    public function postRouteIsRegisteredAndDispatched(): void
    {
        $router = $this->makeRouter();

        $router->post('/users', fn(Request $req) => new Response('Created', 201));

        $response = $router->dispatch($this->makeRequest('POST', '/users'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Created', (string) $response->getBody());
    }

    #[Test]
    public function getRouteDoesNotMatchPostRequest(): void
    {
        $router = $this->makeRouter();

        $router->get('/resource', fn(Request $req) => new Response('OK', 200));

        $response = $router->dispatch($this->makeRequest('POST', '/resource'));

        // Method Not Allowed or Not Found — either way not 200.
        $this->assertNotSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Named routes — URL generation
    // -------------------------------------------------------------------------

    #[Test]
    public function namedRouteGeneratesCorrectUrl(): void
    {
        $router = $this->makeRouter();

        $router->get('/users/{id}', fn() => new Response('', 200))->name('users.show');

        $url = $router->route('users.show', ['id' => 7]);

        $this->assertSame('/users/7', $url);
    }

    #[Test]
    public function namedRouteWithoutParametersGeneratesCorrectUrl(): void
    {
        $router = $this->makeRouter();

        $router->get('/dashboard', fn() => new Response('', 200))->name('dashboard');

        $url = $router->route('dashboard');

        $this->assertSame('/dashboard', $url);
    }

    #[Test]
    public function unknownNamedRouteThrowsException(): void
    {
        $router = $this->makeRouter();

        $this->expectException(\InvalidArgumentException::class);

        $router->route('route.that.does.not.exist');
    }

    // -------------------------------------------------------------------------
    // 404 — route not found
    // -------------------------------------------------------------------------

    #[Test]
    public function dispatchReturns404ForUnknownPath(): void
    {
        $router = $this->makeRouter();

        $router->get('/exists', fn() => new Response('OK', 200));

        $response = $router->dispatch($this->makeRequest('GET', '/does-not-exist'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function dispatchReturns404WhenNoRoutesAreRegistered(): void
    {
        $router = $this->makeRouter();

        $response = $router->dispatch($this->makeRequest('GET', '/anything'));

        $this->assertSame(404, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Route groups — prefix
    // -------------------------------------------------------------------------

    #[Test]
    public function routeGroupAppliesPrefixToAllChildRoutes(): void
    {
        $router = $this->makeRouter();

        $router->group(['prefix' => '/api/v1'], function (Router $r) {
            $r->get('/users', fn() => new Response('users list', 200));
            $r->get('/posts', fn() => new Response('posts list', 200));
        });

        $usersResponse = $router->dispatch($this->makeRequest('GET', '/api/v1/users'));
        $postsResponse = $router->dispatch($this->makeRequest('GET', '/api/v1/posts'));

        $this->assertSame(200, $usersResponse->getStatusCode());
        $this->assertSame(200, $postsResponse->getStatusCode());
    }

    #[Test]
    public function routeGroupDoesNotMatchWithoutPrefix(): void
    {
        $router = $this->makeRouter();

        $router->group(['prefix' => '/api'], function (Router $r) {
            $r->get('/users', fn() => new Response('OK', 200));
        });

        // Requesting the bare path without the group prefix should 404.
        $response = $router->dispatch($this->makeRequest('GET', '/users'));

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function nestedGroupsCombinePrefixes(): void
    {
        $router = $this->makeRouter();

        $router->group(['prefix' => '/api'], function (Router $r) {
            $r->group(['prefix' => '/v2'], function (Router $r2) {
                $r2->get('/items', fn() => new Response('items', 200));
            });
        });

        $response = $router->dispatch($this->makeRequest('GET', '/api/v2/items'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Method Not Allowed (405)
    // -------------------------------------------------------------------------

    #[Test]
    public function dispatchReturns405WhenMethodNotAllowed(): void
    {
        $router = $this->makeRouter();

        $router->post('/submit', fn() => new Response('Created', 201));

        // Sending GET to a POST-only route.
        $response = $router->dispatch($this->makeRequest('GET', '/submit'));

        // The router must respond with 405, not 200 or 404.
        $this->assertSame(405, $response->getStatusCode());
    }
}
