<?php

namespace KhalsaJio\AI\Nexus\Provider;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Core\Injector\Injector;
use KhalsaJio\AI\Nexus\Util\CacheManager;
use KhalsaJio\AI\Nexus\Util\RetryManager;
use KhalsaJio\AI\Nexus\LLMClientInterface;

/**
 * Abstract class for LLM clients
 */
abstract class AbstractLLMClient implements LLMClientInterface
{
    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * @var string
     */
    protected string $apiKey;

    /**
     * @var string
     */
    protected string $model;

    /**
     * @var string
     */
    protected string $apiUrl;

    /**
     * @var string
     */
    protected string $apiVersion = 'v1';

    /**
     * @var int Maximum number of tokens to generate
     */
    protected int $maxTokens = 1024;

    /**
     * @var string
     */
    protected ?LoggerInterface $logger;

    public function __construct()
    {
        $this->model = $this->getDefaultModel();

        try {
            $this->logger = Injector::inst()->get(LoggerInterface::class);
        } catch (\Exception $e) {
            $this->logger = null;
        }
    }

    abstract protected function getDefaultModel(): string;

    public static function getClientName(): string
    {
        return strtolower((new \ReflectionClass(static::class))->getShortName());
    }

    public function initiate(): void
    {
        $baseUrl = $this->apiUrl . DIRECTORY_SEPARATOR . $this->getApiVersion() . DIRECTORY_SEPARATOR;

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => $this->getRequestHeaders(),
        ]);
    }

    protected function getRequestHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function setApiKey(string $api_key): void
    {
        $this->apiKey = $api_key;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function setApiVersion(string $api_version): void
    {
        $this->apiVersion = $api_version;
    }

    public function setMaxTokens(int $maxTokens): void
    {
        $this->maxTokens = $maxTokens;
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    public function validate(): bool
    {
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException(static::getClientName() . " API key required");
        }
        return true;
    }

    /**
     * This is a wildcard method to handle any API call
     * @param array $payload
     * @param string $endpoint
     * @param bool $useCache Whether to use the cache (default true)
     * @return array
     */
    public function chat(array $payload, string $endpoint, bool $useCache = true)
    {
        if (empty($this->client)) {
            return $this->formatError(new \RuntimeException('Client not initialized'));
        }

        if (empty($endpoint)) {
            return $this->formatError(new \InvalidArgumentException('Endpoint is required'));
        }

        if (!isset($payload['model'])) {
            $payload['model'] = $this->getModel();
        }

        // Check cache first if caching is enabled
        if ($useCache && !isset($payload['stream'])) {
            $cachedResponse = CacheManager::getCachedResponse($payload, $endpoint, static::getClientName());
            if ($cachedResponse !== null) {
                if ($this->logger) {
                    $this->logger->debug(static::getClientName() . ' API: Using cached response');
                }
                return $cachedResponse;
            }
        }

        try {
            $response = $this->client->post($endpoint, [
                RequestOptions::JSON => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \RuntimeException('API error: ' . $result['error']['message']);
            }

            $formattedResponse = $this->formatResponse($result);

            // Cache the response if appropriate
            if ($useCache && !isset($payload['stream'])) {
                CacheManager::cacheResponse($payload, $endpoint, static::getClientName(), $formattedResponse);
            }

            return $formattedResponse;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error(static::getClientName() . ' API error: ' . $e->getMessage());
            }

            return $this->formatError($e);
        }
    }

    protected function formatResponse(array $result): array
    {
        return [
            'success' => true,
            'content' => $this->extractContent($result),
            'usage' => $this->extractUsage($result),
            'error' => null,
            'provider' => static::getClientName(),
            'model' => $this->model,
            'raw' => $result,
        ];
    }

    protected function formatError($e): array
    {
        if ($this->logger) {
            $this->logger->error(static::getClientName() . ' API error: ' . $e->getMessage());
        }

        return [
            'success' => false,
            'errorType' => get_class($e),
            'content' => '',
            'usage' => [],
            'error' => $e->getMessage(),
            'provider' => static::getClientName(),
            'model' => $this->model,
        ];
    }

    abstract protected function extractContent(array $response): string;

    abstract protected function extractUsage(array $response): array;

    /**
     * Stream API responses for long-running requests
     * @param array $payload
     * @param string $endpoint
     * @param StreamResponseHandler $handler Handler for stream events
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    public function streamChat(array $payload, string $endpoint, StreamResponseHandler $handler)
    {
        if (empty($this->client)) {
            $handler->handleError(new \RuntimeException('Client not initialized'));
            return;
        }

        if (empty($endpoint)) {
            $handler->handleError(new \InvalidArgumentException('Endpoint is required'));
            return;
        }

        // Set model if not provided
        if (!isset($payload['model'])) {
            $payload['model'] = $this->getModel();
        }

        // Set streaming flag for the API
        $payload['stream'] = true;

        try {
            $response = $this->client->post($endpoint, [
                RequestOptions::JSON => $payload,
                RequestOptions::STREAM => true,
                RequestOptions::ON_HEADERS => function (Response $response) {
                    // Check if the response status code is not successful
                    if ($response->getStatusCode() != 200) {
                        throw new \RuntimeException(
                            'Stream request failed with status code: ' . $response->getStatusCode()
                        );
                    }
                },
                'decode_content' => true,
            ]);

            // Process the stream
            $body = $response->getBody();

            // Track token usage if available
            $usageData = [];

            while (!$body->eof()) {
                $line = trim($this->readLine($body));
                if (!$line || $line === 'data: [DONE]') {
                    continue;
                }

                if (strpos($line, 'data:') === 0) {
                    $jsonData = trim(substr($line, 5));
                    if (empty($jsonData)) {
                        continue;
                    }

                    try {
                        $data = json_decode($jsonData, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $handler->handleChunk($data, static::getClientName(), $this->model);

                            // Collect usage data if present
                            if (!empty($data['usage'])) {
                                $usageData = $this->extractUsage($data);
                            }
                        }
                    } catch (\Exception $e) {
                        if ($this->logger) {
                            $this->logger->error('Error processing stream chunk: ' . $e->getMessage());
                        }
                        continue;
                    }
                }
            }

            // Call completion handler with usage data
            $handler->complete($usageData);
        } catch (RequestException $e) {
            $handler->handleError($e);
            if ($this->logger) {
                $this->logger->error(static::getClientName() . ' Streaming API error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $handler->handleError($e);
            if ($this->logger) {
                $this->logger->error(static::getClientName() . ' Streaming API error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Helper method to read a line from a stream
     *
     * @param \Psr\Http\Message\StreamInterface $stream
     * @return string
     */
    protected function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n" || $byte === "\r") {
                break;
            }
            $buffer .= $byte;
        }
        return $buffer;
    }

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
    ) {
        $retryManager = Injector::inst()->get(RetryManager::class);

        return $retryManager->executeWithRetry(
            function () use ($payload, $endpoint, $useCache) {
                return $this->chat($payload, $endpoint, $useCache);
            },
            $maxRetries,
            $initialBackoff,
            $backoffMultiplier
        );
    }

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
    ) {
        $retryManager = Injector::inst()->get(RetryManager::class);

        $retryManager->executeWithRetry(
            function () use ($payload, $endpoint, $handler) {
                $this->streamChat($payload, $endpoint, $handler);
                return true; // Return a value for the retry manager
            },
            $maxRetries,
            $initialBackoff,
            $backoffMultiplier
        );
    }
}
