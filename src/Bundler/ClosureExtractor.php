<?php

declare(strict_types=1);

namespace Verge\Bundler;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Extracts closure source code and metadata via reflection.
 */
class ClosureExtractor
{
    /**
     * Extract information from a closure.
     */
    public function extract(Closure $closure): ClosureInfo
    {
        $reflection = new ReflectionFunction($closure);

        $sourceCode = $this->extractSourceCode($reflection);
        $isArrowFunction = $this->isArrowFunction($sourceCode);

        return new ClosureInfo(
            parameters: $this->extractParameters($reflection),
            body: $this->extractBody($sourceCode, $isArrowFunction),
            uses: $this->extractUses($reflection),
            returnType: $this->extractReturnType($reflection),
            sourceFile: $reflection->getFileName() ?: '',
            startLine: $reflection->getStartLine() ?: 0,
            endLine: $reflection->getEndLine() ?: 0,
            isArrowFunction: $isArrowFunction,
            isStatic: $reflection->isStatic(),
            bindThis: $reflection->getClosureThis() !== null,
        );
    }

    /**
     * Extract the raw source code of the closure.
     */
    private function extractSourceCode(ReflectionFunction $reflection): string
    {
        $filename = $reflection->getFileName();
        if ($filename === false || !file_exists($filename)) {
            return '';
        }

        $startLine = $reflection->getStartLine();
        $endLine = $reflection->getEndLine();

        if ($startLine === false || $endLine === false) {
            return '';
        }

        $lines = file($filename);
        if ($lines === false) {
            return '';
        }

        $relevantLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode('', $relevantLines);
    }

    /**
     * Check if the closure is an arrow function (fn =>).
     */
    private function isArrowFunction(string $source): bool
    {
        // Look for fn keyword followed by parameters and =>
        return (bool) preg_match('/\bfn\s*\(/', $source);
    }

    /**
     * Extract the body of the closure.
     */
    private function extractBody(string $source, bool $isArrowFunction): string
    {
        if ($isArrowFunction) {
            return $this->extractArrowFunctionBody($source);
        }

        return $this->extractTraditionalClosureBody($source);
    }

    /**
     * Extract body from arrow function: fn($x) => $x * 2
     */
    private function extractArrowFunctionBody(string $source): string
    {
        // Find the => and extract everything after it
        if (preg_match('/=>\s*(.+)$/s', $source, $matches)) {
            $body = trim($matches[1]);

            // Remove trailing characters that aren't part of the expression
            // (commas, semicolons, closing parens from outer context)
            $body = $this->cleanExpressionTail($body);

            return $body;
        }

        return '';
    }

    /**
     * Extract body from traditional closure: function($x) { return $x * 2; }
     */
    private function extractTraditionalClosureBody(string $source): string
    {
        // Find the opening brace and extract content
        $bracePos = strpos($source, '{');
        if ($bracePos === false) {
            return '';
        }

        // Find matching closing brace
        $depth = 0;
        $start = $bracePos + 1;
        $end = $start;

        for ($i = $bracePos; $i < strlen($source); $i++) {
            $char = $source[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        $body = substr($source, $start, $end - $start);

        return trim($body);
    }

    /**
     * Clean trailing characters from an expression.
     */
    private function cleanExpressionTail(string $body): string
    {
        // Track parentheses, brackets, braces depth
        $parenDepth = 0;
        $bracketDepth = 0;
        $braceDepth = 0;
        $inString = false;
        $stringChar = '';
        $lastValidPos = 0;

        for ($i = 0; $i < strlen($body); $i++) {
            $char = $body[$i];
            $prevChar = $i > 0 ? $body[$i - 1] : '';

            // Handle string literals
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $prevChar !== '\\') {
                $inString = false;
            }

            if ($inString) {
                $lastValidPos = $i;
                continue;
            }

            // Track depth
            if ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth--;
                if ($parenDepth < 0) {
                    // We've hit the closing paren of the outer context
                    break;
                }
            } elseif ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth--;
            } elseif ($char === '{') {
                $braceDepth++;
            } elseif ($char === '}') {
                $braceDepth--;
            }

            // Check for statement terminators at depth 0
            if ($parenDepth === 0 && $bracketDepth === 0 && $braceDepth === 0) {
                if ($char === ';' || $char === ',') {
                    break;
                }
            }

            if (!ctype_space($char)) {
                $lastValidPos = $i;
            }
        }

        return trim(substr($body, 0, $lastValidPos + 1));
    }

    /**
     * Extract parameter information.
     *
     * @return array<ParameterInfo>
     */
    private function extractParameters(ReflectionFunction $reflection): array
    {
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $parameters[] = $this->extractParameter($param);
        }

        return $parameters;
    }

    /**
     * Extract information from a single parameter.
     */
    private function extractParameter(ReflectionParameter $param): ParameterInfo
    {
        $type = $param->getType();
        $typeName = null;
        $isBuiltin = true;
        $isNullable = false;

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $isBuiltin = $type->isBuiltin();
            $isNullable = $type->allowsNull() && $typeName !== 'mixed';
        }

        $defaultValue = null;
        $hasDefaultValue = false;

        if ($param->isDefaultValueAvailable()) {
            $hasDefaultValue = true;
            try {
                $defaultValue = $param->getDefaultValue();
            } catch (\ReflectionException $e) {
                // Cannot get default value (e.g., for internal classes)
            }
        }

        return new ParameterInfo(
            name: $param->getName(),
            type: $typeName,
            isBuiltin: $isBuiltin,
            isOptional: $param->isOptional(),
            isVariadic: $param->isVariadic(),
            isPassedByReference: $param->isPassedByReference(),
            defaultValue: $defaultValue,
            hasDefaultValue: $hasDefaultValue,
            isNullable: $isNullable,
        );
    }

    /**
     * Extract variables captured via 'use' clause.
     *
     * @return array<string, mixed>
     */
    private function extractUses(ReflectionFunction $reflection): array
    {
        $uses = [];
        $staticVariables = $reflection->getStaticVariables();

        foreach ($staticVariables as $name => $value) {
            $uses[$name] = $value;
        }

        return $uses;
    }

    /**
     * Extract the return type.
     */
    private function extractReturnType(ReflectionFunction $reflection): ?string
    {
        $returnType = $reflection->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            return $returnType->getName();
        }

        return null;
    }
}
