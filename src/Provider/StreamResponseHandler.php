<?php

namespace KhalsaJio\AI\Nexus\Provider;

/**
 * Interface for handling streaming responses from LLM providers
 */
interface StreamResponseHandler
{
    /**
     * Handle a chunk of data from a streaming response
     *
     * @param mixed $chunk The chunk data from the stream
     * @param string $provider The LLM provider name
     * @param string $model The model being used
     * @return void
     */
    public function handleChunk($chunk, string $provider, string $model): void;

    /**
     * Called when the stream is completed
     *
     * @param array $usage Token usage information if available
     * @return void
     */
    public function complete(array $usage = []): void;

    /**
     * Called when an error occurs during streaming
     *
     * @param \Exception $exception The exception that occurred
     * @return void
     */
    public function handleError(\Exception $exception): void;
}
