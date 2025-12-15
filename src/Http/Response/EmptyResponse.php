<?php

declare(strict_types=1);

namespace Verge\Http\Response;

use Verge\Http\Response;

class EmptyResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(int $status = 204, array $headers = [])
    {
        parent::__construct('', $status, $headers);
    }
}
