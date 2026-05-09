<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * URI prefix applied to all routes defined on the annotated controller class.
 *
 * Usage:
 *   #[Prefix('/admin')]
 *   class AdminController extends BaseController { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Prefix
{
    public function __construct(
        public readonly string $path,
    ) {}
}
