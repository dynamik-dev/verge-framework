<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Verge\Routing\UrlSigner;

/**
 * HTTP Request with DX sugar on top of PSR-7.
 *
 * Can wrap any PSR-7 ServerRequestInterface for full interoperability.
 */
class Request implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    protected array $files;

    /**
     * @param array<string, mixed> $files
     */
    public function __construct(
        protected ServerRequestInterface $inner,
        array $files = [],
    ) {
        $this->files = $files;
    }

    /**
     * Create a new request (backwards-compatible factory).
     *
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $files
     */
    public static function create(
        string $method = 'GET',
        string|UriInterface $uri = '/',
        array $headers = [],
        ?string $body = null,
        array $query = [],
        array $parsedBody = [],
        array $files = []
    ): static {
        $inner = new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $body,
            queryParams: $query,
            parsedBody: $parsedBody,
        );

        /** @phpstan-ignore-next-line */
        return new static($inner, $files);
    }

    /**
     * Wrap any PSR-7 ServerRequest with Verge's DX sugar.
     *
     * @param array<string, mixed> $files
     */
    public static function wrap(ServerRequestInterface $request, array $files = []): static
    {
        /** @phpstan-ignore-next-line */
        return new static($request, $files);
    }

    /**
     * Capture the current request from PHP globals.
     *
     * @return static
     */
    public static function capture(): static
    {
        /** @var string $method */
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        /** @var string $uri */
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();
        $body = file_get_contents('php://input') ?: null;
        /** @var array<string, mixed> $query */
        $query = $_GET;
        /** @var array<string, mixed> $files */
        $files = $_FILES;

        $parsedBody = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (!is_string($contentType)) {
                $contentType = '';
            }
            if (str_contains($contentType, 'application/json')) {
                $decoded = json_decode($body ?? '', true);
                if (is_array($decoded)) {
                    $parsedBody = $decoded;
                }
            } else {
                $parsedBody = $_POST;
            }
        }

        /** @var array<string, string> $cookieParams */
        $cookieParams = $_COOKIE;

        $inner = new ServerRequest(
            method: $method,
            uri: $uri,
            headers: $headers ?: [],
            body: $body,
            serverParams: $_SERVER,
            cookieParams: $cookieParams,
            queryParams: $query,
            parsedBody: $parsedBody,
        );

        /** @phpstan-ignore-next-line */
        return new static($inner, $files);
    }

    /**
     * @return array<string, string|string[]>
     */
    protected static function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                if (is_array($value)) {
                    $headers[$name] = array_map(fn ($v) => is_scalar($v) || $v instanceof \Stringable ? (string) $v : '', $value);
                } else {
                    $headers[$name] = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
                }
            }
        }
        return $headers;
    }

    // =========================================================================
    // DX Sugar Methods
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $body = (string) $this->inner->getBody();
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function body(): ?string
    {
        $body = (string) $this->inner->getBody();
        return $body === '' ? null : $body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $parsedBody = $this->inner->getParsedBody();
        $query = $this->inner->getQueryParams();

        if (is_array($parsedBody) && array_key_exists($key, $parsedBody)) {
            return $parsedBody[$key];
        }

        return $query[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        $query = $this->inner->getQueryParams();
        if ($key === null) {
            return $query;
        }
        return $query[$key] ?? $default;
    }

    public function header(string $key): ?string
    {
        $values = $this->inner->getHeader($key);
        return $values[0] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->inner->getHeaders() as $name => $values) {
            $headers[$name] = $values[0];
        }
        return $headers;
    }

    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) {
            return null;
        }
        /** @var array<string, mixed> $fileData */
        $fileData = $this->files[$key];
        return new UploadedFile($fileData);
    }

    public function method(): string
    {
        return $this->inner->getMethod();
    }

    public function path(): string
    {
        return $this->inner->getUri()->getPath();
    }

    public function url(): string
    {
        return (string) $this->inner->getUri();
    }

    /**
     * Check if the request has a valid signature.
     */
    public function hasValidSignature(?UrlSigner $signer = null): bool
    {
        if ($signer === null) {
            /** @var UrlSigner $signer */
            $signer = app()->make(UrlSigner::class);
        }

        return $signer->verify($this->fullUrl());
    }

    /**
     * Get the full URL including scheme, host, and query string.
     */
    public function fullUrl(): string
    {
        $uri = $this->inner->getUri();
        $url = '';

        if ($uri->getScheme() !== '') {
            $url .= $uri->getScheme() . '://';
        }

        if ($uri->getHost() !== '') {
            $url .= $uri->getHost();
            if ($uri->getPort() !== null) {
                $url .= ':' . $uri->getPort();
            }
        }

        $url .= $uri->getPath() ?: '/';

        if ($uri->getQuery() !== '') {
            $url .= '?' . $uri->getQuery();
        }

        return $url;
    }

    /**
     * Get the inner PSR-7 request.
     */
    public function getInner(): ServerRequestInterface
    {
        return $this->inner;
    }

    // =========================================================================
    // PSR-7 ServerRequestInterface Methods (delegated to inner)
    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->inner->getServerParams();
    }

    /**
     * @return array<string, string>
     */
    public function getCookieParams(): array
    {
        return $this->inner->getCookieParams();
    }

    /**
     * @param array<string, string> $cookies
     */
    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withCookieParams($cookies);
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->inner->getQueryParams();
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withQueryParams($query);
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getUploadedFiles(): array
    {
        return $this->files;
    }

    /**
     * @param array<string, mixed> $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $clone = clone $this;
        $clone->files = $uploadedFiles;
        return $clone;
    }

    /**
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody(): array|object|null
    {
        return $this->inner->getParsedBody();
    }

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withParsedBody($data);
        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->inner->getAttributes();
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $this->inner->getAttribute($name, $default);
    }

    public function withAttribute(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withAttribute($name, $value);
        return $clone;
    }

    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withoutAttribute($name);
        return $clone;
    }

    // =========================================================================
    // PSR-7 RequestInterface Methods (delegated to inner)
    // =========================================================================

    public function getRequestTarget(): string
    {
        return $this->inner->getRequestTarget();
    }

    public function withRequestTarget(string $requestTarget): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withRequestTarget($requestTarget);
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->inner->getMethod();
    }

    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withMethod($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->inner->getUri();
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withUri($uri, $preserveHost);
        return $clone;
    }

    // =========================================================================
    // PSR-7 MessageInterface Methods (delegated to inner)
    // =========================================================================

    public function getProtocolVersion(): string
    {
        return $this->inner->getProtocolVersion();
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withProtocolVersion($version);
        return $clone;
    }

    /**
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->inner->getHeaders();
    }

    public function hasHeader(string $name): bool
    {
        return $this->inner->hasHeader($name);
    }

    /**
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        return $this->inner->getHeader($name);
    }

    public function getHeaderLine(string $name): string
    {
        return $this->inner->getHeaderLine($name);
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withHeader($name, $value);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withAddedHeader($name, $value);
        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withoutHeader($name);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->inner->getBody();
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withBody($body);
        return $clone;
    }

    // =========================================================================
    // Backwards Compatibility
    // =========================================================================

    /**
     * @param array<string, mixed> $query
     */
    public function withQuery(array $query): static
    {
        return $this->withQueryParams($query);
    }
}
