<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::FORBIDDEN, $message, $code, $previous);
    }
}
