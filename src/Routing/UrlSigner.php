<?php

declare(strict_types=1);

namespace Verge\Routing;

use RuntimeException;

class UrlSigner
{
    public function __construct(
        protected string $key,
    ) {
        if ($key === '') {
            throw new RuntimeException('APP_KEY cannot be empty');
        }
    }

    /**
     * Sign a URL with an optional expiration time.
     *
     * @param int|null $expiration Unix timestamp when the signature expires
     */
    public function sign(string $url, ?int $expiration = null): string
    {
        /** @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|false $parsed */
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new RuntimeException("Invalid URL: {$url}");
        }

        // Parse existing query params and flatten to string values
        $query = $this->parseQuery($parsed['query'] ?? '');

        // Add expiration if provided
        if ($expiration !== null) {
            $query['expires'] = (string) $expiration;
        }

        // Build the URL without signature for signing
        $urlToSign = $this->buildUrl($parsed, $query);

        // Generate signature
        $signature = $this->generateSignature($urlToSign);

        // Add signature to query
        $query['signature'] = $signature;

        return $this->buildUrl($parsed, $query);
    }

    /**
     * Verify a signed URL.
     */
    public function verify(string $url): bool
    {
        /** @var array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string}|false $parsed */
        $parsed = parse_url($url);

        if ($parsed === false) {
            return false;
        }

        // Parse query params and flatten to string values
        $query = $this->parseQuery($parsed['query'] ?? '');

        // Must have a signature
        if (!isset($query['signature'])) {
            return false;
        }

        $providedSignature = $query['signature'];

        // Check expiration first if present
        if (isset($query['expires'])) {
            $expires = (int) $query['expires'];
            if (time() > $expires) {
                return false;
            }
        }

        // Remove signature from query to rebuild original signed URL
        unset($query['signature']);

        // Rebuild the URL as it was before signing
        $urlToVerify = $this->buildUrl($parsed, $query);

        // Generate expected signature
        $expectedSignature = $this->generateSignature($urlToVerify);

        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Parse a query string into an array of string values.
     *
     * @return array<string, string>
     */
    protected function parseQuery(string $queryString): array
    {
        if ($queryString === '') {
            return [];
        }

        parse_str($queryString, $parsed);

        $result = [];
        foreach ($parsed as $key => $value) {
            // Flatten arrays to their first value, convert to string
            if (is_array($value)) {
                $value = reset($value);
            }
            $result[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return $result;
    }

    /**
     * Generate an HMAC signature for a URL.
     */
    protected function generateSignature(string $url): string
    {
        return hash_hmac('sha256', $url, $this->key);
    }

    /**
     * Build a URL from parsed components.
     *
     * @param array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, fragment?: string} $parsed
     * @param array<string, string> $query
     */
    protected function buildUrl(array $parsed, array $query): string
    {
        $url = '';

        if (isset($parsed['scheme'])) {
            $url .= $parsed['scheme'] . '://';
        }

        if (isset($parsed['user'])) {
            $url .= $parsed['user'];
            if (isset($parsed['pass'])) {
                $url .= ':' . $parsed['pass'];
            }
            $url .= '@';
        }

        if (isset($parsed['host'])) {
            $url .= $parsed['host'];
        }

        if (isset($parsed['port'])) {
            $url .= ':' . $parsed['port'];
        }

        $url .= $parsed['path'] ?? '/';

        if (!empty($query)) {
            // Sort query params for consistent signatures
            ksort($query);
            $url .= '?' . http_build_query($query);
        }

        if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }

        return $url;
    }
}
