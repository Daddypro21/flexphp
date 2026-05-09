<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a GET route on the annotated controller method.
 *
 * Usage:
 *   #[Get('/articles', name: 'articles.index')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Get extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['GET', 'HEAD'], $name, $middleware);
    }
}
