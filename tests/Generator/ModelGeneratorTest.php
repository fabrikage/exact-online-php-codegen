<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Tests\Generator;

use ExactOnline\CodeGen\Generator\ModelGenerator;
use ExactOnline\CodeGen\Parser\ApiResource;
use ExactOnline\CodeGen\Parser\ApiProperty;
use PHPUnit\Framework\TestCase;

final class ModelGeneratorTest extends TestCase
{
    private ModelGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ModelGenerator();
    }

    public function testGenerateModelCreatesValidPhpClass(): void
    {
        $resource = new ApiResource(
            name: 'Test Account',
            endpoint: '/api/v1/{division}/crm/Accounts',
            description: 'Test account resource',
            properties: [
                new ApiProperty('ID', 'string', 'Primary key', true, false),
                new ApiProperty('Name', 'string', 'Account name', true, false),
                new ApiProperty('IsActive', 'bool', 'Active status', false, true),
            ]
        );

        $code = $this->generator->generateModel($resource);

        // Basic structure checks
        $this->assertStringContainsString('<?php', $code);
        $this->assertStringContainsString('declare(strict_types=1);', $code);
        $this->assertStringContainsString('class TestAccount', $code);
        $this->assertStringContainsString('implements JsonSerializable', $code);
        $this->assertStringContainsString('readonly', $code);

        // Property checks
        $this->assertStringContainsString('public string $id', $code);
        $this->assertStringContainsString('public string $name', $code);
        $this->assertStringContainsString('public ?bool $isActive', $code);

        // Method checks
        $this->assertStringContainsString('public function getId(): string', $code);
        $this->assertStringContainsString('public function getName(): string', $code);
        $this->assertStringContainsString('public function isIsActive(): ?bool', $code);
        $this->assertStringContainsString('public static function fromArray', $code);
        $this->assertStringContainsString('public function toArray(): array', $code);
        $this->assertStringContainsString('public function jsonSerialize(): array', $code);
    }

    public function testGeneratedCodeIsValidPhp(): void
    {
        $resource = new ApiResource(
            name: 'Simple Model',
            endpoint: '/api/v1/{division}/test/SimpleModel',
            description: 'A simple test model',
            properties: [
                new ApiProperty('ID', 'string', 'Identifier', true, false),
                new ApiProperty('Count', 'int', 'Item count', true, false),
            ]
        );

        $code = $this->generator->generateModel($resource);

        // Try to validate syntax (this will throw if invalid)
        $tempFile = tempnam(sys_get_temp_dir(), 'php_test_');
        file_put_contents($tempFile, $code);

        $output = shell_exec("php -l {$tempFile} 2>&1");
        unlink($tempFile);

        $this->assertStringContainsString('No syntax errors detected', $output);
    }
}
