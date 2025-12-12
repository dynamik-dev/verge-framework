<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Verge\Concerns\HasHeaders;

class Request implements RequestInterface
{
    use HasHeaders;
    protected string $method;
    protected Uri $uri;
    protected array $headers;
    protected ?string $body;
    protected array $query;
    protected array $parsedBody;
    protected array $files;
    protected string $protocolVersion = '1.1';

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

    public static function capture(): static
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $headers = function_exists('getallheaders') ? getallheaders() : self::parseHeaders();
        $body = file_get_contents('php://input') ?: null;
        $query = $_GET;
        $files = $_FILES;

        $parsedBody = [];
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                $parsedBody = json_decode($body ?? '', true) ?? [];
            } else {
                $parsedBody = $_POST;
            }
        }

        return new static($method, $uri, $headers, $body, $query, $parsedBody, $files);
    }

    protected static function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    // Edge API methods

    public function json(): array
    {
        if ($this->body === null) {
            return [];
        }
        return json_decode($this->body, true) ?? [];
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
        return new UploadedFile($this->files[$key]);
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

    public function withParsedBody(array $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function withQuery(array $query): static
    {
        $clone = clone $this;
        $clone->query = $query;
        return $clone;
    }
}
