<?php

declare(strict_types=1);

namespace FlexPHP\Http\Middleware;

use FlexPHP\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF (Cross-Site Request Forgery) protection middleware.
 *
 * Safe HTTP methods (GET, HEAD, OPTIONS) pass through without validation.
 * For all other methods the middleware verifies that the request carries a
 * valid token either as `_token` in the request body or as the
 * `X-CSRF-Token` request header.
 *
 * The token is stored in the PHP session under the key `_csrf_token`.
 * A new token is generated automatically when none exists yet.
 *
 * On failure a plain-text 403 Forbidden response is returned and the
 * pipeline is short-circuited (the downstream handlers are not called).
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * HTTP methods that do NOT require CSRF validation.
     *
     * @var string[]
     */
    protected array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Session key used to store the CSRF token.
     */
    protected string $sessionKey = '_csrf_token';

    /**
     * Token length in bytes (before base64-encoding).
     */
    protected int $tokenBytes = 32;

    // -------------------------------------------------------------------------
    // PSR-15
    // -------------------------------------------------------------------------

    /**
     * Process the incoming request.
     *
     * Safe methods are passed straight to the next handler.
     * All other methods must supply a valid CSRF token.
     *
     * @param ServerRequestInterface  $request The incoming PSR-7 request.
     * @param RequestHandlerInterface $handler The next handler in the pipeline.
     *
     * @return ResponseInterface Either the downstream response or a 403 error.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->startSessionIfNeeded();

        // Safe methods are allowed without a token.
        if ($this->isMethodSafe(strtoupper($request->getMethod()))) {
            return $handler->handle($request);
        }

        // Validate the CSRF token.
        if (!$this->isTokenValid($request)) {
            return $this->forbiddenResponse();
        }

        return $handler->handle($request);
    }

    // -------------------------------------------------------------------------
    // Token management
    // -------------------------------------------------------------------------

    /**
     * Retrieve the current session CSRF token, generating a new one if absent.
     *
     * @return string The base64url-encoded CSRF token.
     */
    public function getToken(): string
    {
        $this->startSessionIfNeeded();

        if (empty($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = $this->generateToken();
        }

        return $_SESSION[$this->sessionKey];
    }

    /**
     * Generate a cryptographically secure random token.
     *
     * @return string A URL-safe base64-encoded random string.
     */
    protected function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes($this->tokenBytes)), '+/', '-_'), '=');
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether the given HTTP method is safe (does not mutate state).
     *
     * @param string $method Uppercased HTTP method string.
     *
     * @return bool True if the method does not require CSRF validation.
     */
    protected function isMethodSafe(string $method): bool
    {
        return in_array($method, $this->safeMethods, true);
    }

    /**
     * Check whether the request contains a valid CSRF token.
     *
     * The token is looked up in two places (in order):
     *   1. `_token` field in the parsed request body (form submissions).
     *   2. `X-CSRF-Token` request header (AJAX / fetch requests).
     *
     * @param ServerRequestInterface $request The PSR-7 request to inspect.
     *
     * @return bool True if the token matches the session token.
     */
    protected function isTokenValid(ServerRequestInterface $request): bool
    {
        $sessionToken = $_SESSION[$this->sessionKey] ?? null;

        if ($sessionToken === null) {
            // No token in session — cannot be valid.
            return false;
        }

        $requestToken = $this->extractToken($request);

        if ($requestToken === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks.
        return hash_equals($sessionToken, $requestToken);
    }

    /**
     * Extract the CSRF token from the request body or header.
     *
     * @param ServerRequestInterface $request The PSR-7 request.
     *
     * @return string|null The token string, or null if not present.
     */
    protected function extractToken(ServerRequestInterface $request): ?string
    {
        // 1. Check parsed body (_token field).
        $body = $request->getParsedBody();

        if (is_array($body) && isset($body['_token'])) {
            return (string) $body['_token'];
        }

        // 2. Check the X-CSRF-Token header.
        $headerValues = $request->getHeader('X-CSRF-Token');

        if (!empty($headerValues)) {
            return $headerValues[0];
        }

        return null;
    }

    /**
     * Build a 403 Forbidden PSR-7 response.
     *
     * @return ResponseInterface A plain-text 403 response.
     */
    protected function forbiddenResponse(): ResponseInterface
    {
        return Response::html('403 Forbidden — CSRF token mismatch.', 403)
            ->getPsrResponse();
    }

    /**
     * Start the PHP session if it has not already been started.
     * Avoids "session already started" warnings in test environments.
     */
    protected function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
