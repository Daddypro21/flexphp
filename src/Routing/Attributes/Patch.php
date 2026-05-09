<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a PATCH route on the annotated controller method.
 *
 * Usage:
 *   #[Patch('/articles/{id:\d+}', name: 'articles.patch')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Patch extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['PATCH'], $name, $middleware);
    }
}
