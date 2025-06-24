<?php

namespace KhalsaJio\AI\Nexus;

use KhalsaJio\AI\Nexus\Provider\StreamResponseHandler;

interface LLMClientInterface
{
    /**
     * Initiate the HTTP client
     */
    public function initiate(): void;

    /**
     * Get client display name
     */
    public static function getClientName(): string;

    /**
     * Set the model to use
     */
    public function setModel(string $model): void;

    /**
     * Get the model being used
     */
    public function getModel(): string;

    /**
     * Set the API key for the client
     */
    public function setApiKey(string $api_key): void;

    /**
     * Get the API key for the client
     */
    public function getApiKey(): string;

    /**
     * Set maximum number of tokens for this request
     */
    public function setMaxTokens(int $maxTokens): void;

    /**
     * Get maximum number of tokens
     */
    public function getMaxTokens(): int;

    /**
     * Validate client configuration
     */
    public function validate(): bool;

    /**
     * This is a wildcard method to handle any API call
     * @param array $payload
     * @param string $endpoint
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return array
     */
    public function chat(array $payload, string $endpoint);

    /**
     * Stream API responses for long-running requests
     * @param array $payload
     * @param string $endpoint
     * @param StreamResponseHandler $handler Handler for stream events
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    public function streamChat(array $payload, string $endpoint, StreamResponseHandler $handler);

    /**
     * Makes an API call with retry logic and exponential backoff
     * 
     * @param array $payload
     * @param string $endpoint
     * @param bool $useCache Whether to use the cache (default true)
     * @param int $maxRetries Maximum number of retry attempts (null to use config value)
     * @param int $initialBackoff Initial backoff time in milliseconds (null to use config value)
     * @param float $backoffMultiplier Multiplier for subsequent backoff times (null to use config value)
     * @return array
     * @throws \Exception If all retry attempts fail
     */
    public function chatWithRetry(
        array $payload, 
        string $endpoint, 
        bool $useCache = true,
        int $maxRetries = null,
        int $initialBackoff = null,
        float $backoffMultiplier = null
    );

    /**
     * Stream API responses with retry logic and exponential backoff
     * 
     * @param array $payload
     * @param string $endpoint
     * @param StreamResponseHandler $handler Handler for stream events
     * @param int $maxRetries Maximum number of retry attempts (null to use config value)
     * @param int $initialBackoff Initial backoff time in milliseconds (null to use config value)
     * @param float $backoffMultiplier Multiplier for subsequent backoff times (null to use config value)
     * @throws \Exception If all retry attempts fail
     * @return void
     */
    public function streamChatWithRetry(
        array $payload, 
        string $endpoint, 
        StreamResponseHandler $handler,
        int $maxRetries = null,
        int $initialBackoff = null,
        float $backoffMultiplier = null
    );
}
