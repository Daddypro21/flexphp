<?php

declare(strict_types=1);

namespace FlexPHP\Routing\Attributes;

use Attribute;

/**
 * Middleware applied to all routes on the annotated controller class or method.
 *
 * On a class  → applies to every route in the controller.
 * On a method → applies only to that specific route (merged with class middleware).
 *
 * Usage:
 *   #[Middleware(AuthMiddleware::class)]
 *   #[Middleware(AuthMiddleware::class, AdminMiddleware::class)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /** @var string[] */
    public readonly array $classes;

    public function __construct(string ...$classes)
    {
        $this->classes = $classes;
    }
}
