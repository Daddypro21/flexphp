<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Registers a POST route on the annotated controller method.
 *
 * Usage:
 *   #[Post('/articles', name: 'articles.store')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Post extends Route
{
    public function __construct(
        string $path,
        ?string $name       = null,
        array  $middleware  = [],
    ) {
        parent::__construct($path, ['POST'], $name, $middleware);
    }
}
