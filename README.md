# SilverStripe AI Nexus

This module provides a bare skeleton integration for the SilverStripe CMS with OpenAI and Claude
LLMs. It is designed to be a starting point for developers to build their own AI-powered features
and tools within the SilverStripe CMS. It includes a basic setup for making API calls to OpenAI and Claude.

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

KhalsaJio\AltGenerator\LLMClient:
  default_client: KhalsaJio\AI\Nexus\Provider\OpenAI # or "KhalsaJio\AI\Nexus\Provider\Claude" - The default LLM client to use - required

SilverStripe\Core\Injector\Injector:
    KhalsaJio\AI\Nexus\Provider\OpenAI: # or "KhalsaJio\AI\Nexus\Provider\Claude"
        properties:
            ApiKey: '`OPENAI_API_KEY`' # can be set in .env file - required
            Model: 'gpt-4o-mini-2024-07-18' # default - optional

```

## Usage

This module serves as a foundation, enabling developers to integrate bespoke Artificial Intelligence (AI) capabilities and tools within the SilverStripe CMS. It offers a fundamental configuration for interacting with the API of different LLM providers like OpenAI and Claude. However, it does not include specific, pre-built features; developers are expected to build these themselves upon this base.

To interact with the LLMs, developers can utilise the provided `chat()` method. This method requires two arguments:

1. `$payload`: An array containing the input data structured according to the requirements of the target LLM API (e.g., prompts, model parameters).
2. `$endpoint`: A string specifying the exact API endpoint URL to which the request should be sent.

Furthermore, developers have the flexibility to extend the base `OpenAI` and `Claude` classes. This allows for the addition of custom functionality, such as specialised data pre-processing, response handling, or unique features tailored to specific needs. Once extended, these customised methods can still be invoked centrally through the `LLMClient` class, provided the developer assigns their extended client implementation to the `LLMClient`. This promotes a consistent interface for interacting with various LLM services.
