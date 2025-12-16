<?php

declare(strict_types=1);

namespace Verge\Http;

interface RequestHandlerInterface
{
    /**
     * Handle a request and produce a response.
     */
    public function handle(Request $request): Response;
}
