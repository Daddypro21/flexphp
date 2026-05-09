<?php

declare(strict_types=1);

namespace FlexPHP\Http\Middleware;

use FlexPHP\Core\Container;
use FlexPHP\Http\Request;
use FlexPHP\Http\Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PSR-15 middleware pipeline.
 *
 * Middleware is processed in FIFO (first-in, first-out) order — the first
 * middleware added is the outermost wrapper and therefore the first to execute
 * on the way in and the last to execute on the way out.
 *
 * Usage:
 *   $stack = new MiddlewareStack($container);
 *   $stack->add(CsrfMiddleware::class);
 *   $stack->add($myMiddlewareInstance);
 *   $response = $stack->handle($request, fn(Request $r) => $router->dispatch($r));
 */
class MiddlewareStack
{
    /**
     * Ordered list of middleware entries.
     * Each entry is either a class name string or an already-built MiddlewareInterface.
     *
     * @var array<int, string|MiddlewareInterface>
     */
    protected array $middleware = [];

    /**
     * Optional DI container used to instantiate middleware from class names.
     */
    protected ?Container $container;

    /**
     * @param Container|null $container DI container for middleware resolution (optional).
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Add a middleware to the end of the stack.
     *
     * @param string|MiddlewareInterface $middleware A FQCN string or an already-built instance.
     */
    public function add(string|MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    // -------------------------------------------------------------------------
    // Pipeline execution
    // -------------------------------------------------------------------------

    /**
     * Process the request through the middleware stack and return a Response.
     *
     * The $finalHandler is called after all middleware have passed the request
     * forward. It receives the (possibly mutated) Request and must return a Response.
     *
     * @param Request  $request       The incoming HTTP request.
     * @param callable $finalHandler  fn(Request): Response — the core request handler (e.g. router).
     *
     * @return Response The response produced by the stack.
     */
    public function handle(Request $request, callable $finalHandler): Response
    {
        // Build a PSR-15 RequestHandlerInterface that wraps the final handler.
        $coreHandler = $this->buildCoreHandler($finalHandler);

        // Walk the middleware stack in reverse so that the first-added
        // middleware becomes the outermost layer.
        $handler = array_reduce(
            array_reverse($this->middleware),
            function (RequestHandlerInterface $carry, string|MiddlewareInterface $mw): RequestHandlerInterface {
                $resolved = $this->resolveMiddleware($mw);
                return $this->wrapMiddleware($resolved, $carry);
            },
            $coreHandler
        );

        // Convert the FlexPHP Request to a PSR-7 ServerRequestInterface and dispatch.
        $psrResponse = $handler->handle($request->getPsrRequest());

        // Wrap the PSR-7 response in a FlexPHP Response.
        return $this->wrapPsrResponse($psrResponse);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a PSR-15 RequestHandlerInterface that calls the final handler.
     *
     * The final handler receives the FlexPHP Request object, so we reconstruct
     * it from the (potentially mutated) PSR-7 request before calling.
     *
     * @param callable $finalHandler fn(Request): Response
     *
     * @return RequestHandlerInterface
     */
    protected function buildCoreHandler(callable $finalHandler): RequestHandlerInterface
    {
        return new class ($finalHandler) implements RequestHandlerInterface {
            public function __construct(private readonly mixed $handler)
            {
            }

            public function handle(ServerRequestInterface $psrRequest): ResponseInterface
            {
                // Re-wrap PSR-7 request in a FlexPHP Request for the final handler.
                $flexRequest = new \FlexPHP\Http\Request($psrRequest);
                /** @var \FlexPHP\Http\Response $flexResponse */
                $flexResponse = ($this->handler)($flexRequest);
                return $flexResponse->getPsrResponse();
            }
        };
    }

    /**
     * Wrap a single MiddlewareInterface with a RequestHandlerInterface so it
     * can be chained in the pipeline.
     *
     * @param MiddlewareInterface     $middleware The middleware to wrap.
     * @param RequestHandlerInterface $next       The next handler in the chain.
     *
     * @return RequestHandlerInterface A handler that calls $middleware->process(…, $next).
     */
    protected function wrapMiddleware(
        MiddlewareInterface $middleware,
        RequestHandlerInterface $next
    ): RequestHandlerInterface {
        return new class ($middleware, $next) implements RequestHandlerInterface {
            public function __construct(
                private readonly MiddlewareInterface $middleware,
                private readonly RequestHandlerInterface $next
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->middleware->process($request, $this->next);
            }
        };
    }

    /**
     * Resolve a middleware entry to a concrete MiddlewareInterface instance.
     *
     * @param string|MiddlewareInterface $middleware Class name or instance.
     *
     * @return MiddlewareInterface The resolved middleware.
     */
    protected function resolveMiddleware(string|MiddlewareInterface $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if ($this->container !== null) {
            return $this->container->make($middleware);
        }

        return new $middleware();
    }

    /**
     * Wrap a PSR-7 ResponseInterface in a FlexPHP Response.
     *
     * @param ResponseInterface $psrResponse The PSR-7 response.
     *
     * @return Response A FlexPHP Response containing the same status / headers / body.
     */
    protected function wrapPsrResponse(ResponseInterface $psrResponse): Response
    {
        $status  = $psrResponse->getStatusCode();
        $body    = (string) $psrResponse->getBody();
        $headers = [];

        foreach ($psrResponse->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return new Response($body, $status, $headers);
    }
}
