<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class UnsupportedMediaTypeException extends HttpException
{
    public function __construct(string $message = 'Unsupported Media Type', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::UNSUPPORTED_MEDIA_TYPE, $message, $code, $previous);
    }
}
