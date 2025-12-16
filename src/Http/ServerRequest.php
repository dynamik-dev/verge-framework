<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-7 ServerRequestInterface implementation.
 *
 * This is the internal implementation used by Request::capture().
 * For external PSR-7 requests, use Request::wrap() instead.
 */
class ServerRequest implements ServerRequestInterface
{
    protected string $method;
    protected UriInterface $uri;
    /** @var array<string, string[]> */
    protected array $headers = [];
    protected StreamInterface $body;
    protected string $protocolVersion = '1.1';
    /** @var array<string, mixed> */
    protected array $serverParams;
    /** @var array<string, string> */
    protected array $cookieParams;
    /** @var array<string, mixed> */
    protected array $queryParams;
    /** @var array<string, mixed>|object|null */
    protected array|object|null $parsedBody;
    /** @var array<string, mixed> */
    protected array $attributes = [];

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $serverParams
     * @param array<string, string> $cookieParams
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed>|object|null $parsedBody
     */
    public function __construct(
        string $method = 'GET',
        string|UriInterface $uri = '/',
        array $headers = [],
        ?string $body = null,
        array $serverParams = [],
        array $cookieParams = [],
        array $queryParams = [],
        array|object|null $parsedBody = null,
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = new StringStream($body ?? '');
        $this->serverParams = $serverParams;
        $this->cookieParams = $cookieParams;
        $this->queryParams = $queryParams;
        $this->parsedBody = $parsedBody;
    }

    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string[]>
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            $normalized[$name] = is_array($value) ? $value : [$value];
        }
        return $normalized;
    }

    // =========================================================================
    // ServerRequestInterface Methods
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, string>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<string, string> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFiles(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        return clone $this;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }

    // =========================================================================
    // RequestInterface Methods
    // =========================================================================

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target ?: '/';
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->uri = new Uri($requestTarget);
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }

    // =========================================================================
    // MessageInterface Methods
    // =========================================================================

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        $values = $this->getHeader($name);
        return implode(', ', $values);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = is_array($value) ? $value : [$value];
        return $clone;
    }

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

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}
