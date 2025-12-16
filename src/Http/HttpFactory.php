<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * PSR-17 HTTP Factory implementation.
 *
 * Creates PSR-7 compatible request, response, stream, URI, and uploaded file instances.
 */
class HttpFactory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /**
     * Create a new request.
     */
    public function createRequest(string $method, mixed $uri): RequestInterface
    {
        return Request::create(
            method: $method,
            uri: $uri instanceof UriInterface ? $uri : $this->createUri($uri)
        );
    }

    /**
     * Create a new response.
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response('', $code);
    }

    /**
     * Create a new server request.
     *
     * @param array<string, mixed> $serverParams
     */
    public function createServerRequest(string $method, mixed $uri, array $serverParams = []): ServerRequestInterface
    {
        $uriInstance = $uri instanceof UriInterface ? $uri : $this->createUri($uri);

        $inner = new ServerRequest(
            method: $method,
            uri: $uriInstance,
            serverParams: $serverParams,
        );

        return Request::wrap($inner);
    }

    /**
     * Create a new stream from a string.
     */
    public function createStream(string $content = ''): StreamInterface
    {
        return new StringStream($content);
    }

    /**
     * Create a stream from an existing file.
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $resource = fopen($filename, $mode);
        if ($resource === false) {
            throw new \RuntimeException("Failed to open file: {$filename}");
        }
        return $this->createStreamFromResource($resource);
    }

    /**
     * Create a new stream from an existing resource.
     *
     * @param resource $resource
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        $content = stream_get_contents($resource);
        if ($content === false) {
            throw new \RuntimeException('Failed to read from resource');
        }
        return new StringStream($content);
    }

    /**
     * Create a new uploaded file.
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = \UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        return new UploadedFile([
            'tmp_name' => '',
            'size' => $size ?? $stream->getSize() ?? 0,
            'error' => $error,
            'name' => $clientFilename ?? '',
            'type' => $clientMediaType ?? '',
        ], (string) $stream);
    }

    /**
     * Create a new URI.
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
