<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Base route attribute. Attach to a controller method to register a route.
 *
 * Usage:
 *   #[Route('/articles/{id:\d+}', methods: ['GET', 'HEAD'], name: 'articles.show')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    /**
     * @param string             $path       URI pattern (fast-route syntax).
     * @param string[]           $methods    HTTP methods that this route responds to.
     * @param string|null        $name       Optional route name for URL generation.
     * @param string[]           $middleware Middleware class names applied to this route.
     */
    public function __construct(
        public readonly string $path,
        public readonly array  $methods    = ['GET'],
        public readonly ?string $name      = null,
        public readonly array  $middleware = [],
    ) {}
}
