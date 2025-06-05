<?php

namespace KhalsaJio\AI\Nexus\Provider;

/**
 * Default implementation of the StreamResponseHandler interface
 * Can be extended or replaced with custom implementations
 */
class DefaultStreamResponseHandler implements StreamResponseHandler
{
    /**
     * @var string Accumulated content from stream chunks
     */
    protected string $content = '';

    /**
     * @var callable|null Optional callback for each chunk
     */
    protected $chunkCallback = null;

    /**
     * @var callable|null Optional callback for completion
     */
    protected $completeCallback = null;

    /**
     * @var callable|null Optional callback for errors
     */
    protected $errorCallback = null;

    /**
     * Constructor
     *
     * @param callable|null $chunkCallback Callback function for chunk processing
     * @param callable|null $completeCallback Callback function when stream completes
     * @param callable|null $errorCallback Callback function when error occurs
     */
    public function __construct(?callable $chunkCallback = null, ?callable $completeCallback = null, ?callable $errorCallback = null)
    {
        $this->chunkCallback = $chunkCallback;
        $this->completeCallback = $completeCallback;
        $this->errorCallback = $errorCallback;
    }

    /**
     * Handle a chunk of data from a streaming response
     *
     * @param mixed $chunk The chunk data from the stream
     * @param string $provider The LLM provider name
     * @param string $model The model being used
     * @return void
     */
    public function handleChunk($chunk, string $provider, string $model): void
    {
        $text = $this->extractTextFromChunk($chunk, $provider);
        if ($text) {
            $this->content .= $text;

            if ($this->chunkCallback) {
                call_user_func($this->chunkCallback, $text, $chunk, $provider, $model);
            }
        }
    }

    /**
     * Extract text content from a chunk based on provider format
     *
     * @param mixed $chunk The raw chunk data
     * @param string $provider The provider name
     * @return string The extracted text
     */
    protected function extractTextFromChunk($chunk, string $provider): string
    {
        $provider = strtolower($provider);

        // Default implementation for common providers
        switch ($provider) {
            case 'openai':
                // REF: https://platform.openai.com/docs/guides/streaming-responses?api-mode=chat#advanced-use-cases
                return $chunk['choices'][0]['delta']['content'] ?? '';
            case 'claude':
                if (isset($chunk['completion'])) {
                    return $chunk['completion'];
                }

                if (isset($chunk['delta']['text'])) {
                    return $chunk['delta']['text'];
                }

                if (isset($chunk['content'])) {
                    return $chunk['content'];
                }

                if (isset($chunk['choices'][0]['delta']['content'])) {
                    return $chunk['choices'][0]['delta']['content'];
                }

                return '';
            case 'deepseek':
                // DeepSeek uses a format similar to OpenAI
                return $chunk['choices'][0]['delta']['content'] ?? '';
            default:
                return '';
        }
    }

    /**
     * Called when the stream is completed
     *
     * @param array $usage Token usage information if available
     * @return void
     */
    public function complete(array $usage = []): void
    {
        if ($this->completeCallback) {
            call_user_func($this->completeCallback, $this->content, $usage);
        }
    }

    /**
     * Called when an error occurs during streaming
     *
     * @param \Exception $exception The exception that occurred
     * @return void
     */
    public function handleError(\Exception $exception): void
    {
        if ($this->errorCallback) {
            call_user_func($this->errorCallback, $exception);
        }
    }

    /**
     * Get the accumulated content from stream
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
