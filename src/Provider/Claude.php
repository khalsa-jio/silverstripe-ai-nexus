<?php

namespace KhalsaJio\AI\Nexus\Provider;

use KhalsaJio\AI\Nexus\Provider\AbstractLLMClient;

class Claude extends AbstractLLMClient
{
    /**
     * API URL for Anthropic Claude API
     * @var string
     */
    protected string $apiUrl = 'https://api.anthropic.com';

    /**
     * Get the default model to use
     *
     * @return string
     */
    protected function getDefaultModel(): string
    {
        return 'claude-3-haiku-20240307';
    }

    public function getRequestHeaders(): array
    {
        return [
            'x-api-key' => $this->getApiKey(),
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ];
    }

    /**
     * Extract content from the API response
     *
     * @param array $response API response
     * @return string Extracted content
     */
    protected function extractContent(array $response): string
    {
        return trim($response['content'][0]['text'] ?? '');
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
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0
        ];
    }
}
