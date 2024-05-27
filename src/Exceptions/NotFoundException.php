<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not Found', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::NOT_FOUND, $message, $code, $previous);
    }
}
