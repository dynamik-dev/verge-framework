<?php

declare(strict_types=1);

namespace Verge\Http\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Verge\Http\Request;
use Verge\Http\Response;

/**
 * A thin, fast HTTP client implementing PSR-18.
 *
 * Uses cURL for actual HTTP requests.
 */
class Client implements ClientInterface
{
    private ?string $baseUri = null;
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private bool $verifyPeer = true;
    /** @var array<string, string> */
    private array $defaultHeaders = [];

    /**
     * Set a base URI to prepend to relative URLs.
     */
    public function withBaseUri(string $baseUri): static
    {
        $clone = clone $this;
        $clone->baseUri = rtrim($baseUri, '/');
        return $clone;
    }

    /**
     * Set the request timeout in seconds.
     */
    public function withTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }

    /**
     * Set the connection timeout in seconds.
     */
    public function withConnectTimeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->connectTimeout = $seconds;
        return $clone;
    }

    /**
     * Enable or disable SSL peer verification.
     */
    public function withVerifyPeer(bool $verify): static
    {
        $clone = clone $this;
        $clone->verifyPeer = $verify;
        return $clone;
    }

    /**
     * Set default headers for all requests.
     *
     * @param array<string, string> $headers
     */
    public function withDefaultHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->defaultHeaders = $headers;
        return $clone;
    }

    /**
     * Add a default header for all requests.
     */
    public function withDefaultHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->defaultHeaders[$name] = $value;
        return $clone;
    }

    /**
     * Send a PSR-7 request and return a PSR-7 response.
     *
     * @throws NetworkException On network errors (connection failed, timeout)
     * @throws RequestException On request errors (malformed URL)
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = (string) $request->getUri();

        // Prepend base URI if set and URI is relative
        if ($this->baseUri !== null && !preg_match('#^https?://#i', $uri)) {
            $uri = $this->baseUri . '/' . ltrim($uri, '/');
        }

        // Apply default headers (request headers take precedence)
        foreach ($this->defaultHeaders as $name => $value) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $value);
            }
        }

        $ch = curl_init();

        if ($ch === false) {
            throw new ClientException('Failed to initialize cURL');
        }

        try {
            $this->configureCurl($ch, $request, $uri);

            $responseBody = curl_exec($ch);

            if ($responseBody === false) {
                $errno = curl_errno($ch);
                $error = curl_error($ch);

                // Network errors (connection issues, timeouts)
                if (in_array($errno, [
                    CURLE_COULDNT_RESOLVE_HOST,
                    CURLE_COULDNT_CONNECT,
                    CURLE_OPERATION_TIMEOUTED,
                    CURLE_SSL_CONNECT_ERROR,
                ], true)) {
                    throw new NetworkException($request, "Network error: {$error}", $errno);
                }

                throw new RequestException($request, "Request failed: {$error}", $errno);
            }

            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            $rawHeaders = substr((string) $responseBody, 0, $headerSize);
            $body = substr((string) $responseBody, $headerSize);

            $headers = $this->parseHeaders($rawHeaders);

            return new Response($body, $statusCode, $headers);
        } finally {
            curl_close($ch);
        }
    }

    /**
     * Make a GET request.
     *
     * @param array<string, string> $headers
     */
    public function get(string $uri, array $headers = []): ResponseInterface
    {
        return $this->sendRequest($this->createRequest('GET', $uri, $headers));
    }

    /**
     * Make a POST request.
     *
     * @param array<string, string> $headers
     */
    public function post(string $uri, mixed $body = null, array $headers = []): ResponseInterface
    {
        return $this->sendRequest($this->createRequest('POST', $uri, $headers, $body));
    }

    /**
     * Make a PUT request.
     *
     * @param array<string, string> $headers
     */
    public function put(string $uri, mixed $body = null, array $headers = []): ResponseInterface
    {
        return $this->sendRequest($this->createRequest('PUT', $uri, $headers, $body));
    }

    /**
     * Make a PATCH request.
     *
     * @param array<string, string> $headers
     */
    public function patch(string $uri, mixed $body = null, array $headers = []): ResponseInterface
    {
        return $this->sendRequest($this->createRequest('PATCH', $uri, $headers, $body));
    }

    /**
     * Make a DELETE request.
     *
     * @param array<string, string> $headers
     */
    public function delete(string $uri, array $headers = []): ResponseInterface
    {
        return $this->sendRequest($this->createRequest('DELETE', $uri, $headers));
    }

    /**
     * Make a POST request with JSON body.
     *
     * @param array<string, string> $headers
     */
    public function postJson(string $uri, mixed $data, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        return $this->post($uri, $body, $headers);
    }

    /**
     * Make a PUT request with JSON body.
     *
     * @param array<string, string> $headers
     */
    public function putJson(string $uri, mixed $data, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        return $this->put($uri, $body, $headers);
    }

    /**
     * Make a PATCH request with JSON body.
     *
     * @param array<string, string> $headers
     */
    public function patchJson(string $uri, mixed $data, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        return $this->patch($uri, $body, $headers);
    }

    /**
     * Create a Request object.
     *
     * @param array<string, string> $headers
     */
    private function createRequest(string $method, string $uri, array $headers, mixed $body = null): Request
    {
        $bodyString = null;

        if ($body !== null) {
            if (is_array($body)) {
                $bodyString = http_build_query($body);
                $headers['Content-Type'] ??= 'application/x-www-form-urlencoded';
            } elseif (is_string($body)) {
                $bodyString = $body;
            } else {
                $bodyString = json_encode($body, JSON_THROW_ON_ERROR);
                $headers['Content-Type'] ??= 'application/json';
            }
        }

        return new Request($method, $uri, $headers, $bodyString);
    }

    /**
     * Configure cURL handle for the request.
     *
     * @param \CurlHandle $ch
     */
    private function configureCurl($ch, RequestInterface $request, string $uri): void
    {
        /** @phpstan-ignore argument.type */
        curl_setopt_array($ch, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifyPeer,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
        ]);

        // Set headers
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[] = "{$name}: {$value}";
            }
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // Set body
        $body = (string) $request->getBody();
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }

    /**
     * Parse raw HTTP headers into an array.
     *
     * @return array<string, string|string[]>
     */
    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];

        // Split by \r\n and filter empty lines
        $lines = array_filter(explode("\r\n", $rawHeaders), fn ($line) => $line !== '');

        foreach ($lines as $line) {
            // Skip status line (HTTP/1.1 200 OK)
            if (str_starts_with($line, 'HTTP/')) {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                // Handle multiple headers with same name
                if (isset($headers[$name])) {
                    if (!is_array($headers[$name])) {
                        $headers[$name] = [$headers[$name]];
                    }
                    $headers[$name][] = $value;
                } else {
                    $headers[$name] = $value;
                }
            }
        }

        return $headers;
    }
}
