<?php

declare(strict_types=1);

namespace Verge {

    use Verge\Http\Response;

    /**
     * Resolve a class from the container.
     */
    function make(string $abstract, array $parameters = []): mixed
    {
        return Verge::make($abstract, $parameters);
    }

    /**
     * Create a new response instance.
     */
    function response(string $body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($body, $status, $headers);
    }

    /**
     * Create a JSON response.
     */
    function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/json';
        return new Response(json_encode($data), $status, $headers);
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
