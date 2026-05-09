<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a DELETE route on the annotated controller method.
 *
 * Usage:
 *   #[Delete('/articles/{id:\d+}', name: 'articles.destroy')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Delete extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['DELETE'], $name, $middleware);
    }
}
