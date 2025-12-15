<?php

declare(strict_types=1);

namespace Verge\Http\Response;

class FileResponse extends StreamResponse
{
    protected string $path;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $path,
        ?string $contentType = null,
        int $status = 200,
        array $headers = []
    ) {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        if (!is_readable($path)) {
            throw new \RuntimeException("File not readable: {$path}");
        }

        $this->path = $path;
        $contentType ??= mime_content_type($path) ?: 'application/octet-stream';
        $resource = fopen($path, 'rb');

        if ($resource === false) {
            throw new \RuntimeException("Could not open file: {$path}");
        }

        $headers['content-length'] = (string) filesize($path);

        parent::__construct($resource, $contentType, $status, $headers);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
