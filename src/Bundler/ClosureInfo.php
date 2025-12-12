<?php

declare(strict_types=1);

namespace Verge\Bundler;

/**
 * Value object containing extracted closure information.
 */
class ClosureInfo
{
    /**
     * @param array<ParameterInfo> $parameters Closure parameters with types
     * @param string $body The closure body code
     * @param array<string, mixed> $uses Variables captured via 'use' clause
     * @param string|null $returnType Return type if declared
     * @param string $sourceFile Original source file path
     * @param int $startLine Starting line number
     * @param int $endLine Ending line number
     * @param bool $isArrowFunction Whether this is an arrow function (fn =>)
     * @param bool $isStatic Whether closure is declared static
     * @param bool $bindThis Whether closure binds $this
     */
    public function __construct(
        public readonly array $parameters,
        public readonly string $body,
        public readonly array $uses,
        public readonly ?string $returnType,
        public readonly string $sourceFile,
        public readonly int $startLine,
        public readonly int $endLine,
        public readonly bool $isArrowFunction = false,
        public readonly bool $isStatic = false,
        public readonly bool $bindThis = false,
    ) {}

    /**
     * Check if this closure can be converted to a handler class.
     */
    public function isConvertible(): bool
    {
        // Cannot convert closures that bind $this
        if ($this->bindThis) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why this closure cannot be converted.
     */
    public function getSkipReason(): ?string
    {
        if ($this->bindThis) {
            return 'Closure binds $this';
        }

        return null;
    }

    /**
     * Check if the closure has captured variables.
     */
    public function hasUses(): bool
    {
        return count($this->uses) > 0;
    }
}
