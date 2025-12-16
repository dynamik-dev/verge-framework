<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Verge\Concerns\HasHeaders;
use Verge\Routing\UrlSigner;

class Request implements RequestInterface
{
    use HasHeaders;
    protected string $method;
    protected UriInterface $uri;
    /** @var array<string, string[]> */
    protected array $headers;
    protected ?string $body;
    /** @var array<string, mixed> */
    protected array $query;
    /** @var array<string, mixed> */
    protected array $parsedBody;
    /** @var array<string, mixed> */
    protected array $files;
    protected string $protocolVersion = '1.1';

    /**
     * @param array<string, string|string[]> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $files
     */
    public function __construct(
        string $method = 'GET',
        string|UriInterface $uri = '/',
        array $headers = [],
        ?string $body = null,
        array $query = [],
        array $parsedBody = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->headers = $this->normalizeHeaders($headers);
        $this->body = $body;
        $this->query = $query;
        $this->parsedBody = $parsedBody;
        $this->files = $files;
    }

    /**
     * @return static
     */
    public static function capture(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();
        $body = file_get_contents('php://input') ?: null;
        $query = $_GET;
        $files = $_FILES;

        $parsedBody = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            // Ensure content type is string
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

        /** @phpstan-ignore-next-line */
        return new static($method, $uri, $headers, $body, $query, $parsedBody, $files);
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

    // Edge API methods

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->body === null) {
            return [];
        }
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->parsedBody[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function header(string $key): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key][0] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];
        foreach ($this->headers as $name => $values) {
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
        return $this->method;
    }

    public function path(): string
    {
        return $this->uri->getPath();
    }

    public function url(): string
    {
        return (string) $this->uri;
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
        $uri = $this->uri;
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

    // PSR-7 RequestInterface methods

    public function getRequestTarget(): string
    {
        $target = $this->uri->getPath();
        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }
        return $target ?: '/';
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = new Uri($requestTarget);
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }

    // PSR-7 MessageInterface methods

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

    public function getBody(): StreamInterface
    {
        return new StringStream($this->body ?? '');
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function withParsedBody(array $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function withQuery(array $query): static
    {
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }
}
