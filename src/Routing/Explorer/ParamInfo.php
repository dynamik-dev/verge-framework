<?php

declare(strict_types=1);

namespace Verge\Routing\Explorer;

readonly class ParamInfo
{
    public function __construct(
        public string $name,
        public bool $required,
        public ?string $constraint,
    ) {
    }

    /**
     * @return array{name: string, required: bool, constraint: ?string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'required' => $this->required,
            'constraint' => $this->constraint,
        ];
    }
}
