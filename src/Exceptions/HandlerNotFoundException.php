<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;
use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

class HandlerNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(string $class, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("Handler class {$class} not found in container.", $code, $previous);
    }
}
