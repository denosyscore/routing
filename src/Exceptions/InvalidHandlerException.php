<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;
use InvalidArgumentException;

class InvalidHandlerException extends InvalidArgumentException
{
    public function __construct($handler, int $code = 0, ?Throwable $previous = null)
    {
        $type = is_object($handler) ? get_class($handler) : gettype($handler);
        parent::__construct("Invalid route handler of type {$type}.", $code, $previous);
    }
}
