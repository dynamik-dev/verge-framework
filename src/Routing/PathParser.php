<?php

declare(strict_types=1);

namespace Verge\Routing;

use Verge\Routing\Explorer\ParamInfo;

/**
 * Utility for parsing route path parameters.
 * Handles nested braces in constraints like {year:\d{4}}.
 */
final class PathParser
{
    /**
     * Parse path into regex pattern and parameter names.
     * Used by Router for route matching.
     *
     * @return array{string, string[]}
     */
    public static function compile(string $path): array
    {
        $paramNames = [];
        $pattern = $path;
        $offset = 0;

        while (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::)?/', $pattern, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $fullMatchStart = $match[0][1];
            $paramName = $match[1][0];
            $isOptional = isset($match[2][0]);

            // Find closing brace accounting for nested braces
            $pos = $fullMatchStart + strlen($match[0][0]);
            $braceDepth = 1;
            while ($pos < strlen($pattern) && $braceDepth > 0) {
                if ($pattern[$pos] === '{') {
                    $braceDepth++;
                } elseif ($pattern[$pos] === '}') {
                    $braceDepth--;
                }
                $pos++;
            }

            $fullMatchEnd = $pos;
            $fullMatch = substr($pattern, $fullMatchStart, $fullMatchEnd - $fullMatchStart);

            // Extract constraint if present
            $constraint = '[^/]+';
            if (preg_match('/\{[^:}]+\??(:.+)\}$/', $fullMatch, $constraintMatch)) {
                $constraint = substr($constraintMatch[1], 1);
            }

            $paramNames[] = $paramName;

            // Build replacement pattern
            $replaceStart = $fullMatchStart;
            if ($isOptional && $fullMatchStart > 0 && $pattern[$fullMatchStart - 1] === '/') {
                $replaceStart = $fullMatchStart - 1;
                $replacement = "(?:/($constraint))?";
            } elseif ($isOptional) {
                $replacement = "(?:/($constraint))?";
            } else {
                $replacement = "($constraint)";
            }

            $pattern = substr($pattern, 0, $replaceStart) . $replacement . substr($pattern, $fullMatchEnd);
            $offset = $replaceStart + strlen($replacement);
        }

        return ['#^' . $pattern . '$#', $paramNames];
    }

    /**
     * Extract parameter metadata from a path.
     * Used by Routes for introspection.
     *
     * @return ParamInfo[]
     */
    public static function extractParams(string $path): array
    {
        $params = [];
        $offset = 0;

        while (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?(?::)?/', $path, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $paramName = $match[1][0];
            $isOptional = isset($match[2][0]);
            $matchStart = $match[0][1];

            // Find closing brace accounting for nested braces
            $pos = $matchStart + strlen($match[0][0]);
            $braceDepth = 1;
            while ($pos < strlen($path) && $braceDepth > 0) {
                if ($path[$pos] === '{') {
                    $braceDepth++;
                } elseif ($path[$pos] === '}') {
                    $braceDepth--;
                }
                $pos++;
            }

            $fullMatch = substr($path, $matchStart, $pos - $matchStart);

            // Extract constraint if present
            $constraint = null;
            if (preg_match('/\{[^:}]+\??(:.+)\}$/', $fullMatch, $constraintMatch)) {
                $constraint = substr($constraintMatch[1], 1);
            }

            $params[] = new ParamInfo(
                name: $paramName,
                required: !$isOptional,
                constraint: $constraint,
            );

            $offset = $pos;
        }

        return $params;
    }
}
