<?php

declare(strict_types=1);

namespace Denosys\Routing;

enum Priority: int
{
    // Critical priority - always check first
    case HIGHEST = 1000;
    case CRITICAL = 900;

    // High priority - preferred resolvers
    case HIGH = 700;
    case ABOVE_NORMAL = 600;

    // Normal priority - standard resolvers
    case NORMAL = 500;

    // Low priority - fallback resolvers
    case BELOW_NORMAL = 400;
    case LOW = 300;

    // Lowest priority - last resort
    case FALLBACK = 100;
    case LOWEST = 0;

    /**
     * Helper to check if this priority is higher than another
     */
    public function isHigherThan(Priority $other): bool
    {
        return $this->value > $other->value;
    }

    /**
     * Helper to check if this priority is lower than another
     */
    public function isLowerThan(Priority $other): bool
    {
        return $this->value < $other->value;
    }
}
