<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Parser;

use Dom\HTMLDocument;
use Dom\Element;
use Dom\NodeList;

/**
 * Parser for Exact Online API documentation using PHP 8.4 DOM classes
 */
final class DocumentationParser
{

    /**
     * Parse the main documentation page to extract API resources from the endpoints table
     *
     * @return array<ApiResource>
     */
    public function parseMainPage(string $html): array
    {
        // Suppress DOM warnings since real-world HTML often has issues
        libxml_use_internal_errors(true);

        $document = HTMLDocument::createFromString($html);

        $tables = $document->querySelectorAll('table');

        foreach ($tables as $table) {
            $services = $this->parseApiTable($table);
            if (!empty($services)) {
                return $services;
            }
        }

        return [];
    }

    /**
     * Parse a single API table for endpoint information
     *
     * @return array<ApiResource>
     */
    private function parseApiTable(Element $table): array
    {
        $headers = $this->getTableHeaders($table);

        if (count($headers) < 6) {
            return []; // Not the main API table
        }

        $headerTexts = array_map(fn($th) => trim($th->textContent), iterator_to_array($headers));
        if (!$this->isApiEndpointsTable($headerTexts)) {
            return [];
        }

        return $this->parseApiTableRows($table);
    }

    /**
     * Get table headers, handling different HTML structures
     */
    private function getTableHeaders(Element $table): NodeList
    {
        $headers = $table->querySelectorAll('th');

        // The real table uses td elements for headers in a tr.header row
        if (count($headers) === 0) {
            $headerRow = $table->querySelector('tr.header');
            if ($headerRow) {
                return $headerRow->querySelectorAll('td');
            }

            // Fallback: check first row
            $firstRow = $table->querySelector('tr');
            if ($firstRow) {
                return $firstRow->querySelectorAll('td');
            }
        }

        return $headers;
    }

    /**
     * Parse all rows from the API table
     *
     * @return array<ApiResource>
     */
    private function parseApiTableRows(Element $table): array
    {
        $services = [];
        $rows = $this->getDataRows($table);

        foreach ($rows as $row) {
            $cells = $row->querySelectorAll('td');
            if (count($cells) >= 6) {
                $service = $this->parseApiTableRow($cells);
                if ($service) {
                    $services[] = $service;
                }
            }
        }

        return $services;
    }

    /**
     * Get data rows from the table, handling different HTML structures
     */
    private function getDataRows(Element $table): NodeList
    {
        // Parse data rows - they have class="filter" in the real HTML
        $rows = $table->querySelectorAll('tr.filter');

        // Fallback if no filter class rows
        if (count($rows) === 0) {
            $rows = $table->querySelectorAll('tbody tr');
        }

        // Final fallback
        if (count($rows) === 0) {
            $rows = $table->querySelectorAll('tr:not(.header)');
        }

        return $rows;
    }

    /**
     * Check if this table contains API endpoint information
     */
    private function isApiEndpointsTable(array $headers): bool
    {
        $expectedHeaders = ['service', 'endpoint', 'resource uri', 'supported methods', 'webhook', 'scope'];
        $headerText = strtolower(implode(' ', $headers));

        foreach ($expectedHeaders as $expected) {
            if (!str_contains($headerText, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse a single row from the API endpoints table
     */
    private function parseApiTableRow(NodeList $cells): ?ApiResource
    {
        if (count($cells) < 6) {
            return null;
        }

        $serviceName = trim($cells[0]->textContent);

        // Endpoint name is often inside an <a> tag in the real HTML
        $endpointCell = $cells[1];
        $linkElement = $endpointCell->querySelector('a');
        $endpointName = $linkElement ? trim($linkElement->textContent) : trim($endpointCell->textContent);

        // Extract detail page URL from the link
        $detailUrl = null;
        if ($linkElement) {
            $href = $linkElement->getAttribute('href');
            if ($href && !str_starts_with($href, 'http')) {
                $detailUrl = 'https://start.exactonline.nl/docs/' . ltrim($href, '/');
            } elseif ($href) {
                $detailUrl = $href;
            }
        }

        $resourceUri = trim($cells[2]->textContent);
        $supportedMethods = trim($cells[3]->textContent);

        // Webhook info is in CSS classes in the real HTML
        $webhookCell = $cells[4];
        $hasWebhook = $webhookCell->getAttribute('class');
        $hasWebhookBool = $hasWebhook && !str_contains($hasWebhook, 'HasNoWebhook');

        $scope = trim($cells[5]->textContent);

        // Skip invalid rows and header rows
        if (
            empty($serviceName) || empty($endpointName) || empty($resourceUri) ||
            stripos($serviceName, 'service') !== false ||
            stripos($endpointName, 'endpoint') !== false ||
            stripos($resourceUri, 'resource uri') !== false ||
            str_contains($resourceUri, 'Resource URI')
        ) {
            return null;
        }

        // Create a resource with table information
        return new ApiResource(
            name: $endpointName,
            endpoint: $resourceUri,
            description: "API endpoint for {$serviceName} - {$endpointName}",
            properties: [], // Will be populated when parsing individual resource pages
            service: $serviceName,
            resourceUri: $resourceUri,
            supportedMethods: $supportedMethods,
            hasWebhook: $hasWebhookBool,
            scope: $scope,
            detailUrl: $detailUrl
        );
    }

    /**
     * Parse a resource page to extract model information
     */
    public function parseResourcePage(string $html): ApiResource
    {
        libxml_use_internal_errors(true);
        $document = HTMLDocument::createFromString($html);

        $resourceName = $this->extractResourceName($document);
        $endpoint = $this->extractEndpoint($document);
        $properties = $this->extractProperties($document);
        $description = $this->extractDescription($document);

        return new ApiResource(
            name: $resourceName,
            endpoint: $endpoint,
            description: $description,
            properties: $properties
        );
    }

    /**
     * Update an existing ApiResource with detailed properties from the detail page
     */
    public function parseDetailPageProperties(string $html, ApiResource $resource): ApiResource
    {
        libxml_use_internal_errors(true);
        $document = HTMLDocument::createFromString($html);

        $properties = $this->extractProperties($document);
        $detailedDescription = $this->extractDescription($document);

        return new ApiResource(
            name: $resource->name,
            endpoint: $resource->endpoint,
            description: $detailedDescription ?: $resource->description,
            properties: $properties,
            service: $resource->service,
            resourceUri: $resource->resourceUri,
            supportedMethods: $resource->supportedMethods,
            hasWebhook: $resource->hasWebhook,
            scope: $resource->scope,
            detailUrl: $resource->detailUrl
        );
    }

    private function extractResourceName(HTMLDocument $document): string
    {
        $h1 = $document->querySelector('h1');
        if ($h1) {
            return trim($h1->textContent);
        }

        $title = $document->querySelector('title');
        if ($title) {
            $titleText = trim($title->textContent);
            return str_replace(' - Exact Online REST API', '', $titleText);
        }

        return 'UnknownResource';
    }

    private function extractEndpoint(HTMLDocument $document): string
    {
        // Look for endpoint information in code blocks or specific sections
        $codeBlocks = $document->querySelectorAll('code, pre');

        foreach ($codeBlocks as $code) {
            $content = trim($code->textContent);
            if (str_contains($content, '/api/v1/') && str_contains($content, '{division}')) {
                return $content;
            }
        }

        return '';
    }

    private function extractDescription(HTMLDocument $document): string
    {
        // Look for the first paragraph after h1
        $h1 = $document->querySelector('h1');
        if ($h1) {
            $nextElement = $h1->nextElementSibling;
            while ($nextElement && strtolower($nextElement->tagName) !== 'p') {
                $nextElement = $nextElement->nextElementSibling;
            }

            if ($nextElement && strtolower($nextElement->tagName) === 'p') {
                return trim($nextElement->textContent);
            }
        }

        return '';
    }

    /**
     * Extract properties from the documentation table
     *
     * @return array<ApiProperty>
     */
    private function extractProperties(HTMLDocument $document): array
    {
        $tables = $document->querySelectorAll('table');

        foreach ($tables as $table) {
            $properties = $this->extractPropertiesFromTable($table);
            if (!empty($properties)) {
                return $properties;
            }
        }

        return [];
    }

    /**
     * Extract properties from a single table
     *
     * @return array<ApiProperty>
     */
    private function extractPropertiesFromTable(Element $table): array
    {
        $rows = $table->querySelectorAll('tr');

        if ($rows->length < 2) {
            return [];
        }

        $firstRowCells = $rows[0]->querySelectorAll('td, th');
        if (count($firstRowCells) < 3) {
            return [];
        }

        $headerTexts = array_map(fn($cell) => strtolower(trim($cell->textContent)), iterator_to_array($firstRowCells));

        if (!$this->isDetailPropertiesTable($headerTexts)) {
            return [];
        }

        return $this->parsePropertiesFromRows($rows);
    }

    /**
     * Parse properties from table rows
     *
     * @param NodeList $rows
     * @return array<ApiProperty>
     */
    private function parsePropertiesFromRows(NodeList $rows): array
    {
        $properties = [];

        // Parse property rows (skip header row)
        for ($i = 1; $i < $rows->length; $i++) {
            $cells = $rows[$i]->querySelectorAll('td');
            if (count($cells) >= 3) {
                $property = $this->parseDetailPropertyRow($cells);
                if ($property) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }

    /**
     * Check if table headers indicate a detail page properties table
     *
     * @param array<string> $headers
     */
    private function isDetailPropertiesTable(array $headers): bool
    {
        $headerText = strtolower(implode(' ', $headers));

        // The real detail pages have columns like: Name, Mandatory, Value, Type, Description
        return str_contains($headerText, 'name') &&
            str_contains($headerText, 'type') &&
            (str_contains($headerText, 'mandatory') || str_contains($headerText, 'description'));
    }

    /**
     * Parse a property row from the detail page table
     *
     * @param iterable<Element> $cells
     */
    private function parseDetailPropertyRow(iterable $cells): ?ApiProperty
    {
        $cellValues = array_map(fn($cell) => trim($cell->textContent), iterator_to_array($cells));

        if (count($cellValues) < 7) {
            return null;
        }

        // Based on the real structure: [Checkbox, Name, Mandatory, PostValue, PutValue, Type, Description]
        $name = $cellValues[1]; // Property name is in column 1 (0-indexed)
        $mandatory = $cellValues[2]; // Mandatory is in column 2
        $type = $cellValues[5]; // Type is in column 5
        $description = $cellValues[6]; // Description is in column 6

        // Skip invalid rows
        if (empty($name) || str_contains($name, '|')) {
            return null;
        }

        // Parse mandatory status
        $isRequired = strtolower($mandatory) === 'true';

        // Check if it's a key field by looking for key icon in the name cell
        $nameCell = iterator_to_array($cells)[1];
        $isKey = $nameCell->querySelector('img[title="Key"]') !== null;

        return new ApiProperty(
            name: $this->convertToCamelCase($name),
            type: $this->normalizeType($type),
            description: $description,
            isRequired: $isRequired || $isKey,
            isNullable: !$isRequired && !$isKey
        );
    }

    /**
     * Convert property name to camelCase
     */
    private function convertToCamelCase(string $name): string
    {
        // Handle common patterns like "ID" -> "id", "IsActive" -> "isActive"
        if ($name === 'ID') {
            return 'id';
        }

        return lcfirst(str_replace([' ', '_', '-'], '', ucwords($name, ' _-')));
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'edm.guid', 'guid', 'uuid' => 'string',
            'edm.int32', 'edm.int16', 'int', 'integer' => 'int',
            'edm.double', 'edm.decimal', 'double', 'decimal', 'float' => 'float',
            'edm.boolean', 'bool', 'boolean' => 'bool',
            'edm.datetime', 'edm.datetimeoffset', 'datetime', 'date' => 'string', // We'll use string for dates
            'edm.string', 'string' => 'string',
            'edm.byte', 'byte' => 'int',
            default => 'string'
        };
    }
}
