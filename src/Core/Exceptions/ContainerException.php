<?php

declare(strict_types=1);

namespace FlexPHP\Core\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Exception thrown when the container fails to resolve a dependency.
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
