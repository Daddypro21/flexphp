<?php

declare(strict_types=1);

namespace FlexPHP\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * HTTP Request wrapper built on top of a PSR-7 ServerRequestInterface.
 *
 * Provides a developer-friendly API over the raw PSR-7 object with helpers
 * for JSON bodies, async requests, query parameters, and uploaded files.
 */
class Request
{
    /**
     * The underlying PSR-7 server request.
     */
    protected ServerRequestInterface $psrRequest;

    /**
     * Parsed body cache (decoded JSON or form data).
     *
     * @var array<string, mixed>|null
     */
    protected ?array $parsedBodyCache = null;

    /**
     * @param ServerRequestInterface $psrRequest The PSR-7 request to wrap.
     */
    public function __construct(ServerRequestInterface $psrRequest)
    {
        $this->psrRequest = $psrRequest;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Create a Request instance from PHP's global state ($_SERVER, $_GET, etc.).
     *
     * Uses the Nyholm PSR-7 ServerRequestCreator under the hood.
     *
     * @return static A new Request populated from globals.
     */
    public static function fromGlobals(): static
    {
        $psr17Factory = new Psr17Factory();

        $creator = new ServerRequestCreator(
            $psr17Factory,  // ServerRequestFactory
            $psr17Factory,  // UriFactory
            $psr17Factory,  // UploadedFileFactory
            $psr17Factory   // StreamFactory
        );

        return new static($creator->fromGlobals());
    }

    // -------------------------------------------------------------------------
    // Basic accessors
    // -------------------------------------------------------------------------

    /**
     * Return the HTTP method (uppercased).
     *
     * @return string e.g. "GET", "POST", "PUT"
     */
    public function getMethod(): string
    {
        return strtoupper($this->psrRequest->getMethod());
    }

    /**
     * Return the full URI as a string.
     *
     * @return string e.g. "http://localhost:8000/users?page=2"
     */
    public function getUri(): string
    {
        return (string) $this->psrRequest->getUri();
    }

    /**
     * Return only the URI path component (without query string).
     *
     * @return string e.g. "/users/42"
     */
    public function getPath(): string
    {
        return $this->psrRequest->getUri()->getPath();
    }

    // -------------------------------------------------------------------------
    // Query string
    // -------------------------------------------------------------------------

    /**
     * Return a single query-string parameter or all parameters.
     *
     * @param string|null $key     Parameter name, or null to get all params.
     * @param mixed       $default Value returned when the key is absent.
     *
     * @return mixed The parameter value, all params (array), or $default.
     */
    public function getQuery(string $key = null, mixed $default = null): mixed
    {
        $params = $this->psrRequest->getQueryParams();

        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Request body
    // -------------------------------------------------------------------------

    /**
     * Return the parsed request body as an associative array.
     *
     * For JSON requests the body is decoded from the raw stream.
     * For form submissions the PSR-7 parsedBody is used.
     *
     * @return array<string, mixed> The parsed body.
     */
    public function getBody(): array
    {
        if ($this->parsedBodyCache !== null) {
            return $this->parsedBodyCache;
        }

        if ($this->isJson()) {
            $raw = (string) $this->psrRequest->getBody();
            $decoded = json_decode($raw, true);
            $this->parsedBodyCache = is_array($decoded) ? $decoded : [];
        } else {
            $parsed = $this->psrRequest->getParsedBody();
            $this->parsedBodyCache = is_array($parsed) ? $parsed : [];
        }

        return $this->parsedBodyCache;
    }

    /**
     * Retrieve a value from the request body or query string (body takes precedence).
     *
     * @param string $key     Parameter name.
     * @param mixed  $default Default value when the key is absent.
     *
     * @return mixed The resolved value or $default.
     */
    public function getInput(string $key, mixed $default = null): mixed
    {
        $body = $this->getBody();

        if (array_key_exists($key, $body)) {
            return $body[$key];
        }

        return $this->getQuery($key, $default);
    }

    // -------------------------------------------------------------------------
    // Headers
    // -------------------------------------------------------------------------

    /**
     * Return the first value of a header by name (case-insensitive), or null.
     *
     * @param string $name Header field name (e.g. "Content-Type").
     *
     * @return string|null The first header value, or null if not present.
     */
    public function getHeader(string $name): ?string
    {
        $values = $this->psrRequest->getHeader($name);
        return $values[0] ?? null;
    }

    // -------------------------------------------------------------------------
    // Type / feature detection
    // -------------------------------------------------------------------------

    /**
     * Determine whether this is a FlexPHP async (partial-render) request.
     * The client must send the header "X-Flex-Async: true".
     *
     * @return bool True when the async header is present and set to "true".
     */
    public function isAsyncRequest(): bool
    {
        return strtolower($this->getHeader('X-Flex-Async') ?? '') === 'true';
    }

    /**
     * Determine whether the request carries a JSON body.
     * Checks the Content-Type header for "application/json".
     *
     * @return bool True when the body is JSON.
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type') ?? '';
        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * Determine whether the HTTP method is POST.
     *
     * @return bool True for POST requests.
     */
    public function isPost(): bool
    {
        return $this->getMethod() === 'POST';
    }

    /**
     * Determine whether the HTTP method is GET.
     *
     * @return bool True for GET requests.
     */
    public function isGet(): bool
    {
        return $this->getMethod() === 'GET';
    }

    // -------------------------------------------------------------------------
    // Files & server params
    // -------------------------------------------------------------------------

    /**
     * Return the uploaded files associated with the request.
     *
     * @return array<string, UploadedFileInterface|array<mixed>> PSR-7 uploaded file tree.
     */
    public function getFiles(): array
    {
        return $this->psrRequest->getUploadedFiles();
    }

    /**
     * Return the server parameters ($_SERVER equivalent).
     *
     * @return array<string, mixed> Server params.
     */
    public function getServerParams(): array
    {
        return $this->psrRequest->getServerParams();
    }

    // -------------------------------------------------------------------------
    // PSR-7 access
    // -------------------------------------------------------------------------

    /**
     * Return the underlying PSR-7 ServerRequestInterface for interoperability.
     *
     * @return ServerRequestInterface The raw PSR-7 request.
     */
    public function getPsrRequest(): ServerRequestInterface
    {
        return $this->psrRequest;
    }
}
