<?php

declare(strict_types=1);

namespace Verge\Bundler;

/**
 * Value object containing parameter information.
 */
class ParameterInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $type,
        public readonly bool $isBuiltin,
        public readonly bool $isOptional,
        public readonly bool $isVariadic,
        public readonly bool $isPassedByReference,
        public readonly mixed $defaultValue = null,
        public readonly bool $hasDefaultValue = false,
        public readonly bool $isNullable = false,
    ) {
    }

    /**
     * Generate the parameter declaration for use in generated code.
     */
    public function toDeclaration(): string
    {
        $parts = [];

        // Type hint
        if ($this->type !== null) {
            $typePrefix = $this->isNullable ? '?' : '';
            $parts[] = $typePrefix . $this->type;
        }

        // Variadic
        if ($this->isVariadic) {
            $parts[] = '...';
        }

        // Reference
        $ref = $this->isPassedByReference ? '&' : '';

        // Name
        $parts[] = $ref . '$' . $this->name;

        $declaration = implode(' ', $parts);

        // Default value
        if ($this->hasDefaultValue) {
            $declaration .= ' = ' . $this->exportDefaultValue();
        }

        return $declaration;
    }

    /**
     * Export the default value as PHP code.
     */
    private function exportDefaultValue(): string
    {
        if ($this->defaultValue === null) {
            return 'null';
        }

        return var_export($this->defaultValue, true);
    }
}
