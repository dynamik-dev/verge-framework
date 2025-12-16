<?php

declare(strict_types=1);

namespace Verge\Http\Response;

use Verge\Http\Response;

class HtmlResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(string $html, int $status = 200, array $headers = [])
    {
        $headers['content-type'] = 'text/html; charset=utf-8';
        parent::__construct($html, $status, $headers);
    }
}
