<?php

declare(strict_types=1);

namespace Verge\Testing;

use Verge\App;
use Verge\Http\Request;
use Verge\Http\Response;

class TestClient
{
    /** @var array<string, string> */
    protected array $headers = [];
    /** @var array<string, string> */
    protected array $cookies = [];

    public function __construct(
        protected App $app
    ) {
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withCookie(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->cookies[$name] = $value;
        return $clone;
    }

    /**
     * @param array<string, mixed> $query
     */
    public function get(string $uri, array $query = []): Response
    {
        return $this->request('GET', $uri, query: $query);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function post(string $uri, array $data = []): Response
    {
        return $this->request('POST', $uri, body: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(string $uri, array $data = []): Response
    {
        return $this->request('PUT', $uri, body: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function patch(string $uri, array $data = []): Response
    {
        return $this->request('PATCH', $uri, body: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function delete(string $uri, array $data = []): Response
    {
        return $this->request('DELETE', $uri, body: $data);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     */
    protected function request(
        string $method,
        string $uri,
        array $query = [],
        array $body = []
    ): Response {
        $headers = $this->headers;

        // Add cookies to headers
        if (!empty($this->cookies)) {
            $cookieString = implode('; ', array_map(
                fn ($k, $v) => "$k=$v",
                array_keys($this->cookies),
                $this->cookies
            ));
            $headers['Cookie'] = $cookieString;
        }

        // Set content type for body
        if (!empty($body) && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        $encoded = !empty($body) ? json_encode($body) : null;
        if ($encoded === false) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }
        $bodyString = $encoded;

        $request = new Request(
            method: $method,
            uri: $uri,
            headers: $headers,
            body: $bodyString,
            query: $query,
            parsedBody: $body
        );

        return $this->app->handle($request);
    }
}
