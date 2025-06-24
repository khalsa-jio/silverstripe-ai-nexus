<?php

namespace KhalsaJio\AI\Nexus\Provider;

use KhalsaJio\AI\Nexus\Provider\AbstractLLMClient;

class OpenAI extends AbstractLLMClient
{
    /**
     * API URL for OpenAI API
     * @var string
     */
    protected string $apiUrl = 'https://api.openai.com';

    protected function getDefaultModel(): string
    {
        return 'gpt-4o-mini-2024-07-18';
    }

    protected function extractContent(array $response): string
    {
        return trim($response['output'][0]['content'][0]['text'] ?? '');
    }

    protected function extractUsage(array $response): array
    {
        return $response['usage'] ?? [];
    }
}
