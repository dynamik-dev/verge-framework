<?php

declare(strict_types=1);

namespace Verge\Http\Response;

use Verge\Http\Response;

class RedirectResponse extends Response
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        $headers['location'] = $url;
        parent::__construct('', $status, $headers);
    }

    public function getTargetUrl(): string
    {
        return $this->getHeaderLine('location');
    }
}
