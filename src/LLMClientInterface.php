<?php

namespace KhalsaJio\AI\Nexus;

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
    public function chat(array $payload,string $endpoint);
}
