<?php

declare(strict_types=1);

namespace Verge\Http\Response;

use Verge\Http\Response;

class StreamResponse extends Response
{
    /** @var resource|null */
    protected $resource;

    /**
     * @param resource $resource
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        $resource,
        string $contentType = 'application/octet-stream',
        int $status = 200,
        array $headers = []
    ) {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('StreamResponse requires a valid resource');
        }

        $this->resource = $resource;
        $headers['content-type'] = $contentType;
        parent::__construct('', $status, $headers);
    }

    /**
     * @return resource|null
     */
    public function getResource()
    {
        return $this->resource;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $values) {
                foreach ($values as $value) {
                    header("$name: $value", false);
                }
            }
        }

        if ($this->resource !== null) {
            fpassthru($this->resource);
            fclose($this->resource);
            $this->resource = null;
        }
    }
}
