<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a route that responds to all HTTP methods.
 *
 * Usage:
 *   #[Any('/webhook', name: 'webhook')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Any extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $name, $middleware);
    }
}
