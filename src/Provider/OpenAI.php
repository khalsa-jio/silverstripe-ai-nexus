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

    /**
     * API version
     * @var string
     */
    protected string $apiVersion = 'v1';

    protected function getDefaultModel(): string
    {
        return 'gpt-4o-mini-2024-07-18';
    }

    public static function getClientName(): string
    {
        return 'OpenAI';
    }

    protected function extractContent(array $response): string
    {
        return trim($result['output'][0]['content'][0]['text'] ?? '');
    }

    protected function extractUsage(array $response): array
    {
        return $result['usage'] ?? [];
    }
}
