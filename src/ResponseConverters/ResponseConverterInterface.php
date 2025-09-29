<?php

declare(strict_types=1);

namespace Denosys\Routing\ResponseConverters;

use Psr\Http\Message\ResponseInterface;

interface ResponseConverterInterface
{
    /**
     * Check if this converter can handle the given value
     */
    public function canConvert(mixed $value): bool;

    /**
     * Convert the value to a PSR-7 response
     */
    public function convert(mixed $value): ResponseInterface;

    /**
     * Get the priority of this converter (higher = checked first)
     */
    public function getPriority(): int;
}
