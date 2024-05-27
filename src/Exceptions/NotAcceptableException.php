<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class NotAcceptableException extends HttpException
{
    public function __construct(string $message = 'Not Acceptable', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::NOT_ACCEPTABLE, $message, $code, $previous);
    }
}
