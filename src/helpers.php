<?php

declare(strict_types=1);

namespace Verge {

    use Verge\Http\Client\Client;
    use Verge\Http\Response;
    use Verge\Http\Response\DownloadResponse;
    use Verge\Http\Response\FileResponse;
    use Verge\Http\Response\HtmlResponse;
    use Verge\Http\Response\JsonResponse;
    use Verge\Http\Response\RedirectResponse;

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
    function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Create an HTML response.
     *
     * @param array<string, string|string[]> $headers
     */
    function html(string $content, int $status = 200, array $headers = []): HtmlResponse
    {
        return new HtmlResponse($content, $status, $headers);
    }

    /**
     * Create a redirect response.
     *
     * @param array<string, string|string[]> $headers
     */
    function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return new RedirectResponse($url, $status, $headers);
    }

    /**
     * Create a file download response.
     */
    function download(string $path, ?string $filename = null, ?string $contentType = null): DownloadResponse
    {
        return new DownloadResponse($path, $filename, $contentType);
    }

    /**
     * Create a file response (inline display).
     */
    function file(string $path, ?string $contentType = null): FileResponse
    {
        return new FileResponse($path, $contentType);
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

    function http(): Client
    {
        /** @var Client */
        return app()->make(Client::class);
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
