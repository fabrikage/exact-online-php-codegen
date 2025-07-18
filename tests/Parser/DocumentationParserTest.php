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

        $this->assertEquals('Bulk CRM Accounts', $resource->name);
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
<head>
    <title>Bulk CRM Accounts - Exact Online REST API</title>
</head>
<body>
    <h1>Bulk CRM Accounts</h1>
    <p>This resource allows bulk operations on CRM accounts.</p>
    <code>/api/v1/{division}/bulk/CRM/Accounts</code>
    <table>
        <thead>
            <tr class="header">
                <td></td>
                <td>Name</td>
                <td>Mandatory</td>
                <td>Value POST</td>
                <td>Value PUT</td>
                <td>Type</td>
                <td>Description</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><input type="checkbox" /></td>
                <td>ID</td>
                <td>true</td>
                <td>true</td>
                <td>false</td>
                <td>Edm.Guid</td>
                <td>Primary key</td>
            </tr>
            <tr>
                <td><input type="checkbox" /></td>
                <td>Name</td>
                <td>true</td>
                <td>true</td>
                <td>true</td>
                <td>Edm.String</td>
                <td>Account name</td>
            </tr>
            <tr>
                <td><input type="checkbox" /></td>
                <td>Code</td>
                <td>false</td>
                <td>true</td>
                <td>true</td>
                <td>Edm.String</td>
                <td>Account code (optional)</td>
            </tr>
        </tbody>
    </table>
</body>
</html>';
    }
}
