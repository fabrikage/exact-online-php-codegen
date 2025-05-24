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
 * Alternative crawler that generates files as resources are parsed (streaming approach)
 */
final class StreamingApiCrawler
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
     * Crawl and generate models with streaming (immediate generation)
     */
    public function crawl(string $outputDirectory): CrawlResult
    {
        $this->logger->info('Starting streaming API documentation crawl');

        try {
            // Step 1: Parse main page to get all resources
            $mainPageHtml = $this->httpClient->fetchPage(self::MAIN_URL);
            $resources = $this->parser->parseMainPage($mainPageHtml);

            $this->logger->info('Found {count} resources from main page', ['count' => count($resources)]);

            // Step 2: Process and generate files incrementally
            $result = $this->processResourcesWithStreaming($resources, $outputDirectory);

            $this->logger->info(
                'Streaming crawl completed - Generated {count} files',
                ['count' => count($result['generatedFiles'])]
            );

            return new CrawlResult(
                resources: $resources,
                detailedResources: $result['detailedResources'],
                generatedFiles: $result['generatedFiles'],
                success: true
            );
        } catch (\Throwable $e) {
            $this->logger->error('Streaming crawl failed: {message}', [
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
     * Process resources and generate files immediately as they're parsed
     *
     * @param array<ApiResource> $resources
     * @return array{detailedResources: array<ApiResource>, generatedFiles: array<string>}
     */
    private function processResourcesWithStreaming(array $resources, string $outputDirectory): array
    {
        $detailedResources = [];
        $generatedFiles = [];
        $urlsToFetch = [];

        // Separate resources with and without detail pages
        foreach ($resources as $resource) {
            if ($resource->detailUrl) {
                $urlsToFetch[$resource->detailUrl] = $resource;
            } else {
                // Generate basic model immediately for resources without detail pages
                $detailedResources[] = $resource;
                $generatedFiles[] = $this->generateSingleModel($resource, $outputDirectory);
            }
        }

        if (empty($urlsToFetch)) {
            return ['detailedResources' => $detailedResources, 'generatedFiles' => $generatedFiles];
        }

        // Process detail pages in batches, generating files as we go
        $urls = array_keys($urlsToFetch);
        $batches = array_chunk($urls, self::MAX_CONCURRENT_REQUESTS);

        foreach ($batches as $batchIndex => $batch) {
            $this->logger->info('Processing and generating batch {index}/{total}', [
                'index' => $batchIndex + 1,
                'total' => count($batches)
            ]);

            $batchResult = $this->processBatchWithGeneration($batch, $urlsToFetch, $outputDirectory);
            $detailedResources = array_merge($detailedResources, $batchResult['resources']);
            $generatedFiles = array_merge($generatedFiles, $batchResult['files']);

            // Add delay between batches
            if ($batchIndex < count($batches) - 1) {
                sleep(1);
            }
        }

        return ['detailedResources' => $detailedResources, 'generatedFiles' => $generatedFiles];
    }

    /**
     * Process a batch and generate files immediately
     *
     * @param array<string> $batch
     * @param array<string, ApiResource> $urlsToFetch
     * @return array{resources: array<ApiResource>, files: array<string>}
     */
    private function processBatchWithGeneration(array $batch, array $urlsToFetch, string $outputDirectory): array
    {
        $batchResources = [];
        $batchFiles = [];

        try {
            $pages = $this->httpClient->fetchPages($batch);

            foreach ($pages as $url => $html) {
                $baseResource = $urlsToFetch[$url];

                try {
                    // Parse detail page
                    $detailedResource = $this->parser->parseDetailPageProperties($html, $baseResource);

                    if (!empty($detailedResource->properties)) {
                        $this->logger->debug('Parsed {count} properties for {name}', [
                            'count' => count($detailedResource->properties),
                            'name' => $baseResource->name
                        ]);
                    }

                    $batchResources[] = $detailedResource;

                    // Generate file immediately
                    $filePath = $this->generateSingleModel($detailedResource, $outputDirectory);
                    $batchFiles[] = $filePath;
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to process resource {name}: {message}', [
                        'name' => $baseResource->name,
                        'message' => $e->getMessage()
                    ]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch batch: {message}', ['message' => $e->getMessage()]);
        }

        return ['resources' => $batchResources, 'files' => $batchFiles];
    }

    /**
     * Generate a single model file and return the path
     */
    private function generateSingleModel(ApiResource $resource, string $outputDirectory): string
    {
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

        $this->logger->debug('Generated model {class} at {path}', [
            'class' => $className,
            'path' => $filePath
        ]);

        return $filePath;
    }
}
