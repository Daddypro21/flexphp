<?php

declare(strict_types=1);

namespace FlexPHP\Http;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP Response wrapper built on top of a PSR-7 ResponseInterface.
 *
 * Provides a fluent API and several static factory methods for common
 * response types (JSON, HTML, redirect, 404 …).
 */
class Response
{
    /**
     * The underlying PSR-7 response object.
     */
    protected ResponseInterface $psrResponse;

    /**
     * @param string               $body    Response body content.
     * @param int                  $status  HTTP status code (default 200).
     * @param array<string,string> $headers Additional headers to set.
     */
    public function __construct(
        string $body = '',
        int $status = 200,
        array $headers = []
    ) {
        $factory = new Psr17Factory();

        $stream = $factory->createStream($body);

        $response = $factory->createResponse($status)->withBody($stream);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        $this->psrResponse = $response;
    }

    // -------------------------------------------------------------------------
    // Fluent mutators
    // -------------------------------------------------------------------------

    /**
     * Return a new instance with an added / replaced header.
     *
     * @param string $name  Header field name.
     * @param string $value Header field value.
     *
     * @return static A new Response instance with the header applied.
     */
    public function setHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->psrResponse = $this->psrResponse->withHeader($name, $value);
        return $clone;
    }

    /**
     * Return a new instance with a different HTTP status code.
     *
     * @param int $code A valid HTTP status code.
     *
     * @return static A new Response instance with the status code applied.
     */
    public function setStatus(int $code): static
    {
        $clone = clone $this;
        $clone->psrResponse = $this->psrResponse->withStatus($code);
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Emit
    // -------------------------------------------------------------------------

    /**
     * Send the response headers and body to the client.
     *
     * Must be called before any output is sent (no preceding echo / print).
     */
    public function send(): void
    {
        // Send the HTTP status line.
        if (!headers_sent()) {
            $statusCode   = $this->psrResponse->getStatusCode();
            $reasonPhrase = $this->psrResponse->getReasonPhrase();
            $protocol     = $this->psrResponse->getProtocolVersion();

            header(
                "HTTP/{$protocol} {$statusCode} {$reasonPhrase}",
                true,
                $statusCode
            );

            // Emit all response headers.
            foreach ($this->psrResponse->getHeaders() as $name => $values) {
                $first = true;
                foreach ($values as $value) {
                    header("{$name}: {$value}", $first);
                    $first = false;
                }
            }
        }

        // Emit the body.
        echo $this->psrResponse->getBody()->getContents();
    }

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    /**
     * Create a JSON response.
     *
     * @param mixed $data   Data to JSON-encode.
     * @param int   $status HTTP status code (default 200).
     *
     * @return static A Response with Content-Type: application/json.
     */
    public static function json(mixed $data, int $status = 200): static
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new static(
            $body !== false ? $body : '{}',
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Create an HTML response.
     *
     * @param string $html   HTML string to send.
     * @param int    $status HTTP status code (default 200).
     *
     * @return static A Response with Content-Type: text/html; charset=UTF-8.
     */
    public static function html(string $html, int $status = 200): static
    {
        return new static(
            $html,
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    /**
     * Create a redirect response.
     *
     * @param string $url    The URL to redirect to.
     * @param int    $status HTTP redirect status code (default 302).
     *
     * @return static A Response with a Location header.
     */
    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, ['Location' => $url]);
    }

    /**
     * Create a 404 Not Found HTML response.
     *
     * @param string $message Body message (default "Not Found").
     *
     * @return static A 404 Response.
     */
    public static function notFound(string $message = 'Not Found'): static
    {
        return static::html($message, 404);
    }

    // -------------------------------------------------------------------------
    // PSR-7 access
    // -------------------------------------------------------------------------

    /**
     * Return the underlying PSR-7 ResponseInterface for interoperability.
     *
     * @return ResponseInterface The raw PSR-7 response.
     */
    public function getPsrResponse(): ResponseInterface
    {
        return $this->psrResponse;
    }

    /**
     * Return the HTTP status code of this response.
     *
     * @return int HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->psrResponse->getStatusCode();
    }

    /**
     * Return the raw body string of this response.
     *
     * @return string Response body.
     */
    public function getBody(): string
    {
        return $this->psrResponse->getBody()->getContents();
    }
}
