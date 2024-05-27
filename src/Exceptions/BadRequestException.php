<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::BAD_REQUEST, $message, $code, $previous);
    }
}
