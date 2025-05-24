<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Crawler;

use ExactOnline\CodeGen\Parser\ApiResource;

/**
 * Result of the API crawling operation
 */
final readonly class CrawlResult
{
    /**
     * @param array<ApiResource> $resources
     * @param array<ApiResource> $detailedResources
     * @param array<string> $generatedFiles
     */
    public function __construct(
        public array $resources,
        public array $detailedResources,
        public array $generatedFiles,
        public bool $success,
        public ?string $error = null
    ) {}

    public function getStatistics(): array
    {
        return [
            'total_resources' => count($this->resources),
            'detailed_resources' => count($this->detailedResources),
            'generated_files' => count($this->generatedFiles),
            'success' => $this->success,
        ];
    }
}
