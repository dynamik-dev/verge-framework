<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Message\StreamInterface;

class StringStream implements StreamInterface
{
    private string $content;
    private int $position = 0;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function close(): void
    {
        $this->content = '';
        $this->position = 0;
    }

    public function detach()
    {
        $this->close();
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->content);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->content);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $size = strlen($this->content);

        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = $size + $offset;
                break;
        }

        $this->position = max(0, min($this->position, $size));
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        $this->content = substr($this->content, 0, $this->position)
            . $string
            . substr($this->content, $this->position + strlen($string));

        $length = strlen($string);
        $this->position += $length;

        return $length;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $result = substr($this->content, $this->position, $length);
        $this->position += strlen($result);
        return $result;
    }

    public function getContents(): string
    {
        $contents = substr($this->content, $this->position);
        $this->position = strlen($this->content);
        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
