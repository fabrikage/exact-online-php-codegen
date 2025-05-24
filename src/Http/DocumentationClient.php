<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Http;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client wrapper for fetching documentation pages
 */
final readonly class DocumentationClient
{
    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'ExactOnline-CodeGen/1.0.0',
            ],
        ]);
    }

    /**
     * Fetch a documentation page
     *
     * @throws GuzzleException
     */
    public function fetchPage(string $url): string
    {
        $response = $this->client->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('Failed to fetch page: %s (Status: %d)', $url, $response->getStatusCode())
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * Fetch multiple pages concurrently
     *
     * @param array<string> $urls
     * @return array<string, string>
     * @throws GuzzleException
     */
    public function fetchPages(array $urls): array
    {
        $promises = [];
        foreach ($urls as $url) {
            $promises[$url] = $this->client->getAsync($url);
        }

        // Use the correct namespace for GuzzleHttp promises
        $responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
        $results = [];

        foreach ($responses as $url => $response) {
            if ($response['state'] === 'fulfilled') {
                /** @var ResponseInterface $httpResponse */
                $httpResponse = $response['value'];
                $results[$url] = $httpResponse->getBody()->getContents();
            }
        }

        return $results;
    }
}
