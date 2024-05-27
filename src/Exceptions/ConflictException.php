<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::CONFLICT, $message, $code, $previous);
    }
}
