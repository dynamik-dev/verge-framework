<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

class UploadedFile implements UploadedFileInterface
{
    private ?string $tmpName;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private bool $moved = false;

    public function __construct(array $file)
    {
        $this->tmpName = $file['tmp_name'] ?? null;
        $this->size = $file['size'] ?? null;
        $this->error = $file['error'] ?? UPLOAD_ERR_OK;
        $this->clientFilename = $file['name'] ?? null;
        $this->clientMediaType = $file['type'] ?? null;
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        if ($this->tmpName === null) {
            throw new RuntimeException('No temporary file available');
        }

        return new StringStream(file_get_contents($this->tmpName));
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('Uploaded file has already been moved');
        }

        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error');
        }

        if ($this->tmpName === null) {
            throw new RuntimeException('No temporary file available');
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Cannot create directory: $dir");
        }

        if (php_sapi_name() === 'cli') {
            rename($this->tmpName, $targetPath);
        } else {
            move_uploaded_file($this->tmpName, $targetPath);
        }

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }

    public function name(): ?string
    {
        return $this->clientFilename;
    }

    public function type(): ?string
    {
        return $this->clientMediaType;
    }

    public function size(): ?int
    {
        return $this->size;
    }

    public function path(): ?string
    {
        return $this->tmpName;
    }
}
