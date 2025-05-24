<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Parser;

/**
 * Represents a property of an API resource
 */
final readonly class ApiProperty
{
    public function __construct(
        public string $name,
        public string $type,
        public string $description,
        public bool $isRequired = true,
        public bool $isNullable = false
    ) {}

    public function getPropertyName(): string
    {
        // Convert PascalCase to camelCase
        $name = $this->name;

        // Handle common acronyms that should be lowercase
        $acronyms = ['ID', 'URL', 'API', 'JSON', 'XML', 'HTML', 'HTTP', 'HTTPS', 'SQL'];
        foreach ($acronyms as $acronym) {
            if ($name === $acronym) {
                return strtolower($acronym);
            }
        }

        // For other cases, just convert first letter to lowercase
        return lcfirst($name);
    }

    public function getGetterName(): string
    {
        $propertyName = $this->getPropertyName();

        if ($this->type === 'bool') {
            return 'is' . ucfirst($propertyName);
        }

        return 'get' . ucfirst($propertyName);
    }

    public function getSetterName(): string
    {
        return 'set' . ucfirst($this->getPropertyName());
    }

    public function getTypeDeclaration(): string
    {
        $type = $this->type;

        if ($this->isNullable) {
            $type = "?{$type}";
        }

        return $type;
    }
}
