<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Crawler;

use ExactOnline\CodeGen\Http\DocumentationClient;
use ExactOnline\CodeGen\Parser\DocumentationParser;
use ExactOnline\CodeGen\Parser\ApiResource;
use ExactOnline\CodeGen\Generator\ModelGenerator;
use Symfony\Component\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main crawler that orchestrates the documentation crawling and code generation
 */
final class ApiCrawler
{
    private const MAIN_URL = 'https://start.exactonline.nl/docs/HlpRestAPIResources.aspx';
    private const MAX_CONCURRENT_REQUESTS = 5;

    public function __construct(
        private readonly DocumentationClient $httpClient,
        private readonly DocumentationParser $parser,
        private readonly ModelGenerator $generator,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {}

    /**
     * Crawl the entire API documentation and generate models
     */
    public function crawl(string $outputDirectory): CrawlResult
    {
        $this->logger->info('Starting API documentation crawl');

        try {
            // Step 1: Parse main page to get all resources from the table
            $mainPageHtml = $this->httpClient->fetchPage(self::MAIN_URL);
            $resources = $this->parser->parseMainPage($mainPageHtml);

            $this->logger->info('Found {count} resources from main page', ['count' => count($resources)]);

            // Step 2: Fetch individual resource pages for detailed property information
            $detailedResources = $this->fetchDetailedResourcePages($resources);
            $this->logger->info('Successfully parsed {count} detailed resources', ['count' => count($detailedResources)]);

            // Step 3: Generate model files
            $generatedFiles = $this->generateModelFiles($detailedResources, $outputDirectory);

            $this->logger->info('Generated {count} model files', ['count' => count($generatedFiles)]);

            return new CrawlResult(
                resources: $resources,
                detailedResources: $detailedResources,
                generatedFiles: $generatedFiles,
                success: true
            );
        } catch (\Throwable $e) {
            $this->logger->error('Crawl failed: {message}', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new CrawlResult(
                resources: [],
                detailedResources: [],
                generatedFiles: [],
                success: false,
                error: $e->getMessage()
            );
        }
    }

    /**
     * Fetch detailed resource pages for resources that have detail page URLs
     *
     * @param array<ApiResource> $resources
     * @return array<ApiResource>
     */
    private function fetchDetailedResourcePages(array $resources): array
    {
        $detailedResources = [];
        $urlsToFetch = $this->extractResourcesWithDetailPages($resources, $detailedResources);

        if (empty($urlsToFetch)) {
            $this->logger->info('No detailed resource pages to fetch');
            return $detailedResources;
        }

        return $this->processBatchedDetailPages($urlsToFetch, $detailedResources);
    }

    /**
     * @param array<ApiResource> $resources
     * @param array<ApiResource> $detailedResources
     * @return array<string, ApiResource>
     */
    private function extractResourcesWithDetailPages(array $resources, array &$detailedResources): array
    {
        $urlsToFetch = [];

        foreach ($resources as $resource) {
            // Look for resources that have detail page URLs
            if ($resource->detailUrl) {
                $urlsToFetch[$resource->detailUrl] = $resource;
            } else {
                // For resources without detail pages, we'll generate minimal models
                // based on the information we have from the table
                $detailedResources[] = $resource;
            }
        }

        return $urlsToFetch;
    }

    /**
     * @param array<string, ApiResource> $urlsToFetch
     * @param array<ApiResource> $detailedResources
     * @return array<ApiResource>
     */
    private function processBatchedDetailPages(array $urlsToFetch, array $detailedResources): array
    {
        $urls = array_keys($urlsToFetch);
        $batches = array_chunk($urls, self::MAX_CONCURRENT_REQUESTS);

        foreach ($batches as $batchIndex => $batch) {
            $this->logger->info('Processing detail batch {index}/{total}', [
                'index' => $batchIndex + 1,
                'total' => count($batches)
            ]);

            $detailedResources = $this->processSingleBatch($batch, $urlsToFetch, $detailedResources);

            // Add delay between batches to be respectful
            if ($batchIndex < count($batches) - 1) {
                sleep(1);
            }
        }

        return $detailedResources;
    }

    /**
     * @param array<string> $batch
     * @param array<string, ApiResource> $urlsToFetch
     * @param array<ApiResource> $detailedResources
     * @return array<ApiResource>
     */
    private function processSingleBatch(array $batch, array $urlsToFetch, array $detailedResources): array
    {
        try {
            $pages = $this->httpClient->fetchPages($batch);

            foreach ($pages as $url => $html) {
                $detailedResources = $this->parseDetailPage($url, $html, $urlsToFetch, $detailedResources);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch detail batch: {message}', ['message' => $e->getMessage()]);
        }

        return $detailedResources;
    }

    /**
     * @param array<string, ApiResource> $urlsToFetch
     * @param array<ApiResource> $detailedResources
     * @return array<ApiResource>
     */
    private function parseDetailPage(string $url, string $html, array $urlsToFetch, array $detailedResources): array
    {
        try {
            $baseResource = $urlsToFetch[$url];

            // Use the new method to parse detail page properties
            $detailedResource = $this->parser->parseDetailPageProperties($html, $baseResource);

            if (!empty($detailedResource->properties)) {
                $detailedResources[] = $detailedResource;
                $this->logger->debug('Parsed {count} properties for {name}', [
                    'count' => count($detailedResource->properties),
                    'name' => $baseResource->name
                ]);
            } else {
                $this->logger->warning('No properties found for detailed resource {name}', ['name' => $baseResource->name]);
                // Still add it, but with empty properties
                $detailedResources[] = $detailedResource;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to parse detailed resource at {url}: {message}', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);
        }

        return $detailedResources;
    }

    /**
     * Generate model files from resources
     *
     * @param array<ApiResource> $resources
     * @return array<string>
     */
    private function generateModelFiles(array $resources, string $outputDirectory): array
    {
        $generatedFiles = [];

        foreach ($resources as $resource) {
            try {
                $code = $this->generator->generateModel($resource);
                $className = $resource->getClassName();

                // Determine file path based on namespace
                $namespace = $resource->getNamespace();
                $relativePath = str_replace(['ExactOnline\\', '\\'], ['', '/'], $namespace);
                $filePath = $outputDirectory . '/' . $relativePath . '/' . $className . '.php';

                // Ensure directory exists
                $this->filesystem->mkdir(dirname($filePath));

                // Write file
                $this->filesystem->dumpFile($filePath, $code);
                $generatedFiles[] = $filePath;

                $this->logger->debug('Generated model {class} at {path}', [
                    'class' => $className,
                    'path' => $filePath
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to generate model for {resource}: {message}', [
                    'resource' => $resource->name,
                    'message' => $e->getMessage()
                ]);
            }
        }

        return $generatedFiles;
    }
}
