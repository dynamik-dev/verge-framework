<?php

declare(strict_types=1);

namespace Verge\Concerns;

trait HasHeaders
{
    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string[]>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = is_array($value) ? $value : [$value];
        }
        return $normalized;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @param string|string[] $value
     */
    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $clone;
    }

    /**
     * @param string|string[] $value
     */
    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $name = strtolower($name);
        $existing = $clone->headers[$name] ?? [];
        $clone->headers[$name] = array_merge($existing, is_array($value) ? $value : [$value]);
        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);
        return $clone;
    }
}
