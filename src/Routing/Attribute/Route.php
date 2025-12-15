<?php

declare(strict_types=1);

namespace Verge\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * @param array<int, string>|string $method
     * @param array<int, string>|string $middleware
     */
    public function __construct(
        public array|string $method,
        public string $path,
        public ?string $name = null,
        public array|string $middleware = []
    ) {
    }
}
