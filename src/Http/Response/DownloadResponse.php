<?php

declare(strict_types=1);

namespace Verge\Http\Response;

class DownloadResponse extends FileResponse
{
    protected string $filename;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $path,
        ?string $filename = null,
        ?string $contentType = null,
        array $headers = []
    ) {
        $this->filename = $filename ?? basename($path);

        parent::__construct($path, $contentType, 200, $headers);

        $this->headers['content-disposition'] = ["attachment; filename=\"{$this->filename}\""];
    }

    public function getFilename(): string
    {
        return $this->filename;
    }
}
