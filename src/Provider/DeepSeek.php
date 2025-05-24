<?php

namespace KhalsaJio\AI\Nexus\Provider;

use KhalsaJio\AI\Nexus\Provider\AbstractLLMClient;

class DeepSeek extends AbstractLLMClient
{
    /**
     * API URL for OpenAI API
     * @var string
     */
    protected string $apiUrl = 'https://api.deepseek.com';

    protected string $apiVersion = 'chat';

    protected function getDefaultModel(): string
    {
        return 'deepseek-chat';
    }

    protected function extractContent(array $response): string
    {
        return trim($response['choices'][0]['message']['content'] ?? '');
    }

    protected function extractUsage(array $response): array
    {
        return $response['usage'] ?? [];
    }
}
