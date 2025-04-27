<?php

namespace KhalsaJio\AI\Nexus\Provider;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use GuzzleHttp\RequestOptions;
use SilverStripe\Core\Injector\Injector;
use KhalsaJio\AI\Nexus\LLMClientInterface;

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

    public function initiate(): void
    {
        $baseUrl = $this->apiUrl . '/' . $this->getApiVersion();

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
     * @return array
     */
    public function chat(array $payload, string $endpoint)
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

        try {
            $response = $this->client->post($endpoint, [
                RequestOptions::JSON => $payload
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \RuntimeException('API error: ' . $result['error']['message']);
            }

            return $this->formatResponse($result);
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
}
