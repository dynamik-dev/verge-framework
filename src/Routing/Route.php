<?php

declare(strict_types=1);

namespace Verge\Routing;

use Verge\Concerns\HasMiddleware;

class Route
{
    use HasMiddleware;

    protected ?string $name = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly mixed $handler,
        public readonly string $pattern,
        public readonly array $paramNames = []
    ) {}

    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function matches(string $path): ?array
    {
        if (preg_match($this->pattern, $path, $matches)) {
            $params = [];
            foreach ($this->paramNames as $index => $name) {
                $value = $matches[$index + 1] ?? null;
                // Only include non-empty values (handles optional params)
                if ($value !== null && $value !== '') {
                    $params[$name] = $value;
                }
            }
            return $params;
        }
        return null;
    }
}
