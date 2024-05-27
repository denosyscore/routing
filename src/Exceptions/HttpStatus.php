<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

enum HttpStatus: int
{
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case METHOD_NOT_ALLOWED = 405;
    case NOT_ACCEPTABLE = 406;
    case CONFLICT = 409;
    case GONE = 410;
    case LENGTH_REQUIRED = 411;
    case PRECONDITION_FAILED = 412;
    case UNSUPPORTED_MEDIA_TYPE = 415;
    case EXPECTATION_FAILED = 417;
    case IM_A_TEAPOT = 418;
    case UNPROCESSABLE_CONTENT = 422;
    case PRECONDITION_REQUIRED = 428;
    case TOO_MANY_REQUESTS = 429;
    case UNAVAILABLE_FOR_LEGAL_REASONS = 451;

    public function getReasonPhrase(): string
    {
        return match($this) {
            HttpStatus::BAD_REQUEST => 'Bad Request',
            HttpStatus::UNAUTHORIZED => 'Unauthorized',
            HttpStatus::FORBIDDEN => 'Forbidden',
            HttpStatus::NOT_FOUND => 'Not Found',
            HttpStatus::METHOD_NOT_ALLOWED => 'Method Not Allowed',
            HttpStatus::NOT_ACCEPTABLE => 'Not Acceptable',
            HttpStatus::CONFLICT => 'Conflict',
            HttpStatus::GONE => 'Gone',
            HttpStatus::LENGTH_REQUIRED => 'Length Required',
            HttpStatus::PRECONDITION_FAILED => 'Precondition Failed',
            HttpStatus::UNSUPPORTED_MEDIA_TYPE => 'Unsupported Media Type',
            HttpStatus::EXPECTATION_FAILED => 'Expectation Failed',
            HttpStatus::IM_A_TEAPOT => 'I\'m a teapot',
            HttpStatus::UNPROCESSABLE_CONTENT => 'Unprocessable Content',
            HttpStatus::PRECONDITION_REQUIRED => 'Precondition Required',
            HttpStatus::TOO_MANY_REQUESTS => 'Too Many Requests',
            HttpStatus::UNAVAILABLE_FOR_LEGAL_REASONS => 'Unavailable For Legal Reasons',
        };
    }
}
