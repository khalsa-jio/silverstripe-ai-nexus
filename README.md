# SilverStripe AI Nexus

This module provides a bare skeleton integration for the SilverStripe CMS with various LLM providers including OpenAI, Claude, and DeepSeek. It is designed to be a starting point for developers to build their own AI-powered features and tools within the SilverStripe CMS. It includes a basic setup for making API calls to these LLM providers, with both standard and streaming response handling capabilities.

## Requirements

- SilverStripe 5.0 or later
- PHP 8.0 or later
- Guzzle HTTP client

## Installation

You can install the module using Composer. Run the following command in your SilverStripe project root:

```bash
composer require khalsa-jio/silverstripe-ai-nexus
```

## Configuration

You can configure the module using YAML file. Either create a new YAML file in your `app/_config` directory or add the configuration to an existing YAML file.

```yaml
---
Name: ai-nexus
---

KhalsaJio\AI\Nexus\LLMClient:
  default_client: KhalsaJio\AI\Nexus\Provider\OpenAI # Select your preferred LLM provider: OpenAI, Claude, or DeepSeek

SilverStripe\Core\Injector\Injector:
    # OpenAI Configuration
    KhalsaJio\AI\Nexus\Provider\OpenAI:
        properties:
            ApiKey: '`OPENAI_API_KEY`' # can be set in .env file - required
            Model: 'gpt-4o-mini-2024-07-18' # default - optional

    # Claude Configuration
    KhalsaJio\AI\Nexus\Provider\Claude:
        properties:
            ApiKey: '`ANTHROPIC_API_KEY`' # can be set in .env file - required
            Model: 'claude-3-haiku-20240307' # default - optional

    # DeepSeek Configuration
    KhalsaJio\AI\Nexus\Provider\DeepSeek:
        properties:
            ApiKey: '`DEEPSEEK_API_KEY`' # can be set in .env file - required
            Model: 'deepseek-chat' # default - optional

```

## Usage

This module serves as a foundation, enabling developers to integrate bespoke Artificial Intelligence (AI) capabilities and tools within the SilverStripe CMS. It offers a fundamental configuration for interacting with the API of different LLM providers like OpenAI, Claude, and DeepSeek. However, it does not include specific, pre-built features; developers are expected to build these themselves upon this base.

### Basic Usage

To interact with the LLMs, developers can utilise the provided `chat()` method. This method requires two arguments:

1. `$payload`: An array containing the input data structured according to the requirements of the target LLM API (e.g., prompts, model parameters).
2. `$endpoint`: A string specifying the exact API endpoint URL to which the request should be sent.

```php
use KhalsaJio\AI\Nexus\LLMClient;

// Create a client instance
$client = LLMClient::create();

// Prepare payload for OpenAI
$payload = [
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Tell me about SilverStripe CMS.']
    ],
    'max_tokens' => 500
];

// Make API call
$result = $client->chat($payload, 'chat/completions');

// Access the response content
$content = $result['content'];
```

### Streaming Responses

For long-running requests, you can use the `streamChat()` method to receive content incrementally:

```php
use KhalsaJio\AI\Nexus\LLMClient;
use KhalsaJio\AI\Nexus\Provider\DefaultStreamResponseHandler;

$client = LLMClient::create();

// Create a stream handler with callbacks
$handler = new DefaultStreamResponseHandler(
    // Chunk callback (called for each chunk received)
    function ($text, $chunk, $provider, $model) {
        echo $text; // Output each chunk as it arrives
    },
    // Complete callback (called when streaming is complete)
    function ($fullContent, $usage) {
        echo "\nCompleted! Used {$usage['output_tokens']} tokens.\n";
    },
    // Error callback (called if an error occurs)
    function ($exception) {
        echo "Error: " . $exception->getMessage();
    }
);

$payload = [
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => 'Write a detailed guide about using SilverStripe CMS.']
    ],
    'max_tokens' => 1000,
    'stream' => true // Must be true for streaming
];

// Stream the response
$client->streamChat($payload, 'chat/completions', $handler);
```

### Token Management

When working with LLM requests, be mindful of token limits for your chosen model. Each model has specific context window limits that include both input and output tokens. Check the LLM provider's documentation for the latest information on token limits.

```php
// Example of setting a reasonable token limit
$payload = [
    'messages' => [
        ['role' => 'system', 'content' => 'You are a helpful assistant.'],
        ['role' => 'user', 'content' => $prompt]
    ],
    'max_tokens' => 1000 // Adjust based on your model's capabilities
];
```

### Response Caching

The module includes automatic caching of responses to reduce API usage:

```php
// Use cache (default)
$result = $client->chat($payload, 'chat/completions', true);

// Skip cache
$result = $client->chat($payload, 'chat/completions', false);
```

## Advanced Caching

This module provides a robust caching system for LLM responses to optimize API usage, reduce latency, and manage costs. The caching system is configurable at multiple levels:

### Cache Configuration

You can configure caching behavior in `_config/cache.yml`:

```yaml
KhalsaJio\AI\Nexus\Util\CacheManager:
  default_ttl: 3600 # 1 hour , Default TTL for cached responses (in seconds)
  enable_caching: true # Enable or disable caching globally
  enable_statistics: true # Enable statistics tracking
  provider_settings:  # Provider-specific cache settings
    openai:
      enable_caching: true
      ttl: 7200 # 2 hours for OpenAI
    claude:
      enable_caching: true
      ttl: 3600 # 1 hour for Claude
  endpoint_settings: # Endpoint-specific cache settings (overrides provider settings)
    'chat/completions':
      enable_caching: true
      ttl: 3600
    'embeddings':
      enable_caching: true
      ttl: 86400 # 24 hours for embeddings
```

### Custom Cache Backend

By default, the module uses SilverStripe's default cache implementation. You can configure a custom cache backend:

```yaml
SilverStripe\Core\Injector\Injector:
  Psr\SimpleCache\CacheInterface.LLMResponseCache:
    factory: SilverStripe\RedisCache\RedisCacheFactory
    constructor:
      namespace: "LLMResponseCache"
    properties:
      ttl: 3600
```

### Usage in Code

In your application code, you can control caching behavior:

```php
// Using the LLMClient
$client = LLMClient::create();

// Use cache (default)
$response = $client->chat($payload, 'chat/completions');

// Skip cache
$response = $client->chat($payload, 'chat/completions', false);

// Direct cache management
use KhalsaJio\AI\Nexus\Util\CacheManager;

// Check if a response is cached
if (CacheManager::isCached($payload, 'chat/completions', 'openai')) {
    // Response exists in cache
}

// Clear all cached responses
CacheManager::clearCache();

// Get cache statistics
$stats = CacheManager::getStatistics();
```

### Safety and Content Filtering

The `SafetyManager` utility helps ensure responsible AI usage:

```php
use KhalsaJio\AI\Nexus\Util\SafetyManager;

// Add safety instructions to messages
$messages = SafetyManager::addSafetyInstructions($messages);

// Check for potentially sensitive content
$warnings = SafetyManager::checkContent($response);
if (!empty($warnings)) {
    // Handle warnings
}

// Filter sensitive information from text
$filteredContent = SafetyManager::filterSensitiveInfo($response);
```

### Extensibility

Developers have the flexibility to extend the base `OpenAI` and `Claude` classes. This allows for the addition of custom functionality, such as specialised data pre-processing, response handling, or unique features tailored to specific needs. Once extended, these customised methods can still be invoked centrally through the `LLMClient` class, provided the developer assigns their extended client implementation to the `LLMClient`. This promotes a consistent interface for interacting with various LLM services.
