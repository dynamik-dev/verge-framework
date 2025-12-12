<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Verge\Concerns\HasHeaders;

class Response implements ResponseInterface
{
    use HasHeaders;
    protected string $body;
    protected int $status;
    protected array $headers;
    protected string $reasonPhrase = '';
    protected string $protocolVersion = '1.1';

    private const REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    public function __construct(
        string $body = '',
        int $status = 200,
        array $headers = []
    ) {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $this->normalizeHeaders($headers);
        $this->reasonPhrase = self::REASON_PHRASES[$status] ?? '';
    }

    // Fluent API

    public function header(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[strtolower($name)] = [$value];
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(): array
    {
        return json_decode($this->body, true) ?? [];
    }

    // PSR-7 ResponseInterface methods

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $clone = clone $this;
        $clone->status = $code;
        $clone->reasonPhrase = $reasonPhrase ?: (self::REASON_PHRASES[$code] ?? '');
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
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
        return new StringStream($this->body);
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = (string) $body;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $values) {
                foreach ($values as $value) {
                    header("$name: $value", false);
                }
            }
        }

        echo $this->body;
    }
}
