<?php

declare(strict_types=1);

namespace Verge\Middleware;

use Verge\Http\Request;
use Verge\Http\Response;

class CorsMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if ($request->method() === 'OPTIONS') {
            return new Response('', 204, $this->corsHeaders());
        }

        $response = $next($request);

        foreach ($this->corsHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
