<?php

declare(strict_types=1);

namespace Verge\Http\Response;

use Verge\Http\Response;

class JsonResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     * @throws \InvalidArgumentException If data cannot be encoded to JSON
     */
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('JSON encode failed: ' . $e->getMessage(), 0, $e);
        }

        $headers['content-type'] = 'application/json';
        parent::__construct($json, $status, $headers);
    }
}
