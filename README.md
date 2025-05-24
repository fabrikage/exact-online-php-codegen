# Exact Online Code Generator

Tired of manually creating PHP models for the Exact Online API? This tool crawls their documentation and generates all the classes for you. It's built with PHP 8.4 and creates clean, type-safe models that are ready to use.

## What it does

- Crawls the official Exact Online REST API docs
- Creates PHP 8.4 classes with proper types and readonly properties
- Handles all 424+ API endpoints automatically
- Follows PSR standards so your IDE will love it
- Runs fast with concurrent requests
- Gives you detailed logs so you know what's happening

## Getting started

You'll need PHP 8.4 and Composer. That's it.

```bash
composer install
```

## How to use it

### Quick start

```bash
# Generate all models in the 'models' folder
./bin/exactonline-codegen models/

# Want to see what's happening? Add verbose mode
./bin/exactonline-codegen models/ -v

# Enable streaming mode (generate files as endpoints are crawled)
./bin/exactonline-codegen models/ --streaming

# Save logs for later
./bin/exactonline-codegen models/ --log-file=generation.log
```

When it runs, you'll see something like this:

```
Exact Online API Code Generator
===============================
 Using PHP 8.4.1
 🔄 Standard mode: All endpoints crawled first, then files generated
 Output directory: /path/to/models

Starting crawl...
 [OK] Code generation completed successfully!

 Duration: 3.2 seconds
 Resources found: 424
 Files generated: 424
```

### Streaming Mode 🚀

By default, the generator crawls all endpoints first, then generates all files at once. This is reliable but you only see file generation at the very end.

With streaming mode (`--streaming`), files are generated as soon as each batch of endpoints is crawled. This gives you immediate feedback:

```bash
./bin/exactonline-codegen models/ --streaming -v
```

You'll see output like:

```
🚀 Streaming mode: Files will be generated as endpoints are crawled
Processing and generating batch 1/85
Generated model Accounts at /path/to/models/Models/Crm/Accounts.php
Generated model Addresses at /path/to/models/Models/Crm/Addresses.php
Processing and generating batch 2/85
...
```

**When to use streaming mode:**

- ✅ You want immediate feedback on generation progress
- ✅ Working with limited memory environments
- ✅ Testing or debugging specific endpoints

**When to use standard mode:**

- ✅ Production deployments (more reliable)
- ✅ When you need atomic operations (all files or none)
- ✅ Better error recovery

### Use it in your code

If you want to integrate this into your own project:

```php
use ExactOnline\CodeGen\Crawler\ApiCrawler;
// ... other imports

$crawler = new ApiCrawler(
    httpClient: new DocumentationClient(),
    parser: new DocumentationParser(),
    generator: new ModelGenerator(),
    filesystem: new Filesystem()
);

$result = $crawler->crawl('/path/to/output');

if ($result->success) {
    echo "Generated {$result->getStatistics()['generated_files']} models\n";
}
```

## What you get

The generated models are clean and easy to work with. Here's what a typical class looks like:

```php
final readonly class Account implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?bool $isActive = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['ID'] ?? ''),
            name: (string) ($data['Name'] ?? ''),
            isActive: $data['IsActive'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'ID' => $this->id,
            'Name' => $this->name,
            'IsActive' => $this->isActive
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
```

The models are organized by service (like `ExactOnline\Models\Crm\Accounts`) and include:

- Readonly properties for immutability
- Proper type hints everywhere
- Easy array conversion methods
- JSON serialization support

## How it works

This thing has four main parts:

1. **DocumentationClient** - Fetches pages from the Exact Online docs
2. **DocumentationParser** - Parses the HTML and extracts all the juicy details
3. **ModelGenerator** - Creates the actual PHP classes
4. **ApiCrawler** - Ties it all together and manages the whole process

It uses PHP 8.4's shiny new DOM classes and readonly properties to make everything fast and type-safe.

## Testing and quality

Want to make sure everything works?

```bash
# Run the tests
vendor/bin/phpunit

# Check test coverage
vendor/bin/phpunit --coverage-html build/coverage

# Static analysis (catches potential bugs)
vendor/bin/phpstan analyse

# Fix code style
vendor/bin/php-cs-fixer fix
```

## Does it actually work?

Yeah, it does! Here's the proof:

- ✅ Successfully tested against the real Exact Online documentation
- ✅ Processed all 424 API endpoints without breaking a sweat
- ✅ Generated 424 working PHP classes organized by service
- ✅ Handles complex HTML structures (because their docs are... interesting)
- ✅ All tests pass, including edge cases
- ✅ Fast execution (usually under 5 seconds for everything)

The tool has been battle-tested against the actual https://start.exactonline.nl/docs/HlpRestAPIResources.aspx page. It deals with their quirky HTML, CSS classes, and various documentation formats without complaining.

Some models end up with 300+ properties (looking at you, CRM Accounts), which shows it's really digging into the detail pages and not just scraping basic info.

## Want to contribute?

Found a bug? Have an idea? Cool!

1. Fork this repo
2. Make your changes
3. Add tests if you're adding features
4. Make sure `vendor/bin/phpunit` still passes
5. Send a pull request

## License

MIT - do whatever you want with it.
