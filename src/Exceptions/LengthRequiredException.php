<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class LengthRequiredException extends HttpException
{
    public function __construct(string $message = 'Length Required', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::LENGTH_REQUIRED, $message, $code, $previous);
    }
}
