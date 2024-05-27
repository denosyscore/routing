<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class GoneException extends HttpException
{
    public function __construct(string $message = 'Gone', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::GONE, $message, $code, $previous);
    }
}
