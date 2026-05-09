<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a PUT route on the annotated controller method.
 *
 * Usage:
 *   #[Put('/articles/{id:\d+}', name: 'articles.update')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Put extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['PUT'], $name, $middleware);
    }
}
