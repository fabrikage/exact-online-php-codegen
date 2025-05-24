<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Tests\Parser;

use ExactOnline\CodeGen\Parser\DocumentationParser;
use PHPUnit\Framework\TestCase;

final class DocumentationParserTest extends TestCase
{
    private DocumentationParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DocumentationParser();
    }

    public function testParseMainPageExtractsServices(): void
    {
        $html = $this->getMockMainPageHtml();
        $resources = $this->parser->parseMainPage($html);

        $this->assertNotEmpty($resources);
        $this->assertCount(3, $resources);

        $crmResource = $resources[0];
        $this->assertEquals('Accounts', $crmResource->name);
        $this->assertEquals('CRM', $crmResource->service);
        $this->assertEquals('/api/v1/{division}/crm/Accounts', $crmResource->resourceUri);
    }

    public function testParseResourcePageExtractsBasicInfo(): void
    {
        $html = $this->getMockResourcePageHtml();
        $resource = $this->parser->parseResourcePage($html);

        $this->assertEquals('Accounts', $resource->name);
        $this->assertStringContainsString('/api/v1/', $resource->endpoint);
        $this->assertNotEmpty($resource->properties);
    }

    public function testParseResourcePageExtractsProperties(): void
    {
        $html = $this->getMockResourcePageHtml();
        $resource = $this->parser->parseResourcePage($html);

        $this->assertCount(3, $resource->properties);

        $firstProperty = $resource->properties[0];
        $this->assertEquals('id', $firstProperty->name); // Updated to camelCase
        $this->assertEquals('string', $firstProperty->type); // Guid normalized to string
        $this->assertTrue($firstProperty->isRequired);
    }

    private function getMockMainPageHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head><title>Exact Online REST API Resources</title></head>
<body>
    <h1>Exact Online REST API Resources</h1>
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Endpoint</th>
                <th>Resource URI</th>
                <th>Supported Methods</th>
                <th>Has webhook</th>
                <th>Scope</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>CRM</td>
                <td>Accounts</td>
                <td>/api/v1/{division}/crm/Accounts</td>
                <td>GET, POST, PUT, DELETE</td>
                <td>This endpoint has a webhook</td>
                <td>Crm accounts</td>
            </tr>
            <tr>
                <td>CRM</td>
                <td>Contacts</td>
                <td>/api/v1/{division}/crm/Contacts</td>
                <td>GET, POST, PUT, DELETE</td>
                <td></td>
                <td>Crm accounts</td>
            </tr>
            <tr>
                <td>Financial</td>
                <td>GLAccounts</td>
                <td>/api/v1/{division}/financial/GLAccounts</td>
                <td>GET, POST, PUT, DELETE</td>
                <td></td>
                <td>Financial generalledgers</td>
            </tr>
        </tbody>
    </table>
</body>
</html>';
    }

    private function getMockResourcePageHtml(): string
    {
        return '<!DOCTYPE html>
<html>
<head><title>Accounts - Exact Online REST API</title></head>
<body>
    <h1>Accounts</h1>
    <p>This resource represents customer and supplier accounts.</p>
    <code>/api/v1/{division}/crm/Accounts</code>
    
    <table>
        <thead>
            <tr>
                <th>Property</th>
                <th>Key</th>
                <th>Required</th>
                <th>Type</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>ID</td>
                <td>true</td>
                <td>true</td>
                <td>Guid</td>
                <td>Primary key</td>
            </tr>
            <tr>
                <td>Name</td>
                <td>false</td>
                <td>true</td>
                <td>String</td>
                <td>Account name</td>
            </tr>
            <tr>
                <td>Code</td>
                <td>false</td>
                <td>false</td>
                <td>String</td>
                <td>Account code (optional)</td>
            </tr>
        </tbody>
    </table>
</body>
</html>';
    }
}
