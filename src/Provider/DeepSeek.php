<?php

namespace KhalsaJio\AI\Nexus\Provider;

use KhalsaJio\AI\Nexus\Provider\AbstractLLMClient;

class DeepSeek extends AbstractLLMClient
{
    /**
     * API URL for DeepSeek API
     * @var string
     */
    protected string $apiUrl = 'https://api.deepseek.com';

    /**
     * API version
     * @var string
     */
    protected string $apiVersion = 'v1';

    /**
     * Get the default model to use
     *
     * @return string
     */
    protected function getDefaultModel(): string
    {
        return 'deepseek-chat';
    }

    /**
     * Get client name
     *
     * @return string
     */
    public static function getClientName(): string
    {
        return 'DeepSeek';
    }

    /**
     * Extract content from the API response
     *
     * @param array $response API response
     * @return string Extracted content
     */
    protected function extractContent(array $response): string
    {
        return trim($response['choices'][0]['message']['content'] ?? '');
    }

    /**
     * Extract usage information from the API response
     *
     * @param array $response API response
     * @return array Extracted usage information
     */
    protected function extractUsage(array $response): array
    {
        return [
            'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
            'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
            'total_tokens' => $response['usage']['total_tokens'] ?? 0,
        ];
    }
}
