<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class ImATeapotException extends HttpException
{
    public function __construct(string $message = 'I\'m a teapot', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::IM_A_TEAPOT, $message, $code, $previous);
    }
}
