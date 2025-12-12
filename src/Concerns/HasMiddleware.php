<?php

declare(strict_types=1);

namespace Verge\Concerns;

trait HasMiddleware
{
    /** @var array<callable|string|object> */
    protected array $middleware = [];

    public function use(callable|string|object $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * @return array<callable|string|object>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}
