<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Parser;

/**
 * Represents an API resource with its properties
 */
final readonly class ApiResource
{
    /**
     * @param array<ApiProperty> $properties
     */
    public function __construct(
        public string $name,
        public string $endpoint,
        public string $description,
        public array $properties,
        public ?string $service = null,
        public ?string $resourceUri = null,
        public ?string $supportedMethods = null,
        public ?bool $hasWebhook = null,
        public ?string $scope = null,
        public ?string $detailUrl = null
    ) {}

    public function getClassName(): string
    {
        // Convert resource name to PascalCase class name
        $name = preg_replace('/[^a-zA-Z0-9]/', ' ', $this->name);
        $name = ucwords($name);
        return str_replace(' ', '', $name);
    }

    public function getNamespace(): string
    {
        // Use the service name if available (extracted from the main table)
        if ($this->service) {
            $serviceName = ucfirst(strtolower(trim($this->service)));
            return "ExactOnline\\Models\\{$serviceName}";
        }

        // Fallback: Extract service from endpoint
        if (preg_match('/\/api\/v1\/\{?division\}?\/([^\/]+)/', $this->endpoint, $matches)) {
            $service = ucfirst(strtolower($matches[1]));
            return "ExactOnline\\Models\\{$service}";
        }

        return 'ExactOnline\\Models';
    }
}
