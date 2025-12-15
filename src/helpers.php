<?php

declare(strict_types=1);

namespace Verge {

    use Verge\Http\Response;

    /**
     * Resolve a class from the container.
     *
     * @param array<string, mixed> $parameters
     */
    function make(string $abstract, array $parameters = []): mixed
    {
        return Verge::make($abstract, $parameters);
    }

    /**
     * Create a new response instance.
     *
     * @param array<string, string|string[]> $headers
     */
    function response(string $body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, $headers);
    }

    /**
     * Create a JSON response.
     *
     * @param array<string, string|string[]> $headers
     */
    function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/json';
        $json = json_encode($data);

        if ($json === false) {
            throw new \InvalidArgumentException('JSON encode failed: ' . json_last_error_msg());
        }

        return new Response($json, $status, $headers);
    }

    /**
     * Create a redirect response.
     */
    function redirect(string $url, int $status = 302): Response
    {
        return new Response('', $status, ['Location' => $url]);
    }

    /**
     * Generate URL for a named route.
     *
     * @param array<string, mixed> $params
     */
    function route(string $name, array $params = []): string
    {
        return Verge::route($name, $params);
    }
}

namespace {
    /**
     * Get the app singleton, creating it if needed.
     */
    function app(): Verge\App
    {
        return Verge\Verge::app() ?? Verge\Verge::create();
    }
}
