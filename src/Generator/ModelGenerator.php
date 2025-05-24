<?php

declare(strict_types=1);

namespace ExactOnline\CodeGen\Generator;

use ExactOnline\CodeGen\Parser\ApiResource;
use ExactOnline\CodeGen\Parser\ApiProperty;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

/**
 * Generates PHP model classes from API resources using Nette PHP Generator
 */
final class ModelGenerator
{
    private PsrPrinter $printer;

    public function __construct()
    {
        $this->printer = new PsrPrinter();
    }

    /**
     * Generate a PHP model class from an API resource
     */
    public function generateModel(ApiResource $resource): string
    {
        $file = new PhpFile();
        $file->setStrictTypes();

        $namespace = $file->addNamespace($resource->getNamespace());
        $namespace->addUse('JsonSerializable');

        $class = $namespace->addClass($resource->getClassName());
        $class->setFinal()
            ->setReadOnly()
            ->addImplement('JsonSerializable');

        // Add class docblock
        $class->addComment($resource->description ?: "Model for {$resource->name}");
        $class->addComment('');
        $class->addComment("Generated from: {$resource->endpoint}");

        // Add constructor
        $constructor = $class->addMethod('__construct');
        $constructorParams = [];

        // Add properties and constructor parameters
        foreach ($resource->properties as $property) {
            $this->addProperty($class, $constructor, $property);
            $constructorParams[] = $property->getPropertyName();
        }

        // Add static factory method
        $this->addFromArrayMethod($class, $resource->properties);

        // Add toArray method
        $this->addToArrayMethod($class, $resource->properties);

        // Add jsonSerialize method
        $this->addJsonSerializeMethod($class);

        // Add getters for each property
        foreach ($resource->properties as $property) {
            $this->addGetter($class, $property);
        }

        return $this->printer->printFile($file);
    }

    private function addProperty(ClassType $class, \Nette\PhpGenerator\Method $constructor, ApiProperty $property): void
    {
        $propertyName = $property->getPropertyName();

        // Add property to class
        $classProperty = $class->addProperty($propertyName)
            ->setType($property->getTypeDeclaration())
            ->setPublic()
            ->setReadOnly();

        if ($property->description) {
            $classProperty->addComment($property->description);
        }

        // Add constructor parameter
        $param = $constructor->addParameter($propertyName)
            ->setType($property->getTypeDeclaration());

        if (!$property->isRequired && $property->isNullable) {
            $param->setDefaultValue(null);
        }
    }

    /**
     * @param array<ApiProperty> $properties
     */
    private function addFromArrayMethod(ClassType $class, array $properties): void
    {
        $method = $class->addMethod('fromArray')
            ->setStatic()
            ->setReturnType('self');

        $method->addParameter('data', []);
        $method->addComment('Create instance from array data');
        $method->addComment('');
        $method->addComment('@param array<string, mixed> $data');

        $body = "return new self(\n";
        $params = [];

        foreach ($properties as $property) {
            $propertyName = $property->getPropertyName();
            $originalName = $property->name;

            if ($property->isNullable) {
                $params[] = "    {$propertyName}: \$data['{$originalName}'] ?? null";
            } else {
                switch ($property->type) {
                    case 'int':
                        $params[] = "    {$propertyName}: (int) (\$data['{$originalName}'] ?? 0)";
                        break;
                    case 'float':
                        $params[] = "    {$propertyName}: (float) (\$data['{$originalName}'] ?? 0.0)";
                        break;
                    case 'bool':
                        $params[] = "    {$propertyName}: (bool) (\$data['{$originalName}'] ?? false)";
                        break;
                    default:
                        $params[] = "    {$propertyName}: (string) (\$data['{$originalName}'] ?? '')";
                }
            }
        }

        $body .= implode(",\n", $params) . "\n);";
        $method->setBody($body);
    }

    /**
     * @param array<ApiProperty> $properties
     */
    private function addToArrayMethod(ClassType $class, array $properties): void
    {
        $method = $class->addMethod('toArray')
            ->setReturnType('array')
            ->addComment('Convert to array');
        $method->addComment('');
        $method->addComment('@return array<string, mixed>');

        $body = "return [\n";
        $items = [];

        foreach ($properties as $property) {
            $propertyName = $property->getPropertyName();
            $originalName = $property->name;
            $items[] = "    '{$originalName}' => \$this->{$propertyName}";
        }

        $body .= implode(",\n", $items) . "\n];";
        $method->setBody($body);
    }

    private function addJsonSerializeMethod(ClassType $class): void
    {
        $method = $class->addMethod('jsonSerialize')
            ->setReturnType('array')
            ->addComment('Specify data which should be serialized to JSON');
        $method->addComment('');
        $method->addComment('@return array<string, mixed>');

        $method->setBody('return $this->toArray();');
    }

    private function addGetter(ClassType $class, ApiProperty $property): void
    {
        $method = $class->addMethod($property->getGetterName())
            ->setReturnType($property->getTypeDeclaration())
            ->setPublic();

        if ($property->description) {
            $method->addComment($property->description);
        }

        $propertyName = $property->getPropertyName();
        $method->setBody("return \$this->{$propertyName};");
    }
}
