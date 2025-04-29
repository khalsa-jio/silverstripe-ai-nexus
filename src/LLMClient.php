<?php

namespace KhalsaJio\AI\Nexus;

use BadMethodCallException;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use KhalsaJio\AI\Nexus\LLMClientInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class LLMClient
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * @config
     * The default LLM client to use
     */
    private static $default_client = null;

    /**
     * @var LLMClientInterface
     */
    private $active_client = null;

    /**
     * @var LoggerInterface
     */
    private $logger = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        try {
            $this->logger = Injector::inst()->get(LoggerInterface::class);
        } catch (\Exception $e) {
            $this->logger = null;
        }

        // Initialize the LLM client
        $this->initiate();
    }

    /**
     * Initialize the LLM client
     *
     * @param string|null $client_class
     * @param string|null $model
     * @return LLMClientInterface|null
     */
    public function initiate($client_class = null, $model = null)
    {
        // Get client class from parameter, config, or default
        $client_class = $client_class ?: LLMClient::default_client();

        if (!$client_class) {
            $this->logError('No LLM client configured');
            return null;
        }

        if (!class_exists($client_class)) {
            $this->logError("Client class '$client_class' does not exist");
            return null;
        }

        try {
            // Create client instance
            $client = Injector::inst()->create($client_class, $model);

            // Validate client implements the interface
            if (!($client instanceof LLMClientInterface)) {
                $this->logError("Client class '$client_class' must implement LLMClientInterface");
                return null;
            }

            // Validate client configuration
            if (!$client->validate()) {
                $this->logError("Client configuration is invalid for $client_class");
                return null;
            }

            // Initialize the client
            $client->initiate();
            $this->active_client = $client;

            return $client;
        } catch (\Exception $e) {
            $this->logError("Failed to initialize LLM client: " . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Log an error message
     *
     * @param string $message
     */
    private function logError($message)
    {
        if ($this->logger) {
            $this->logger->error($message);
        }
    }

    /**
     * Get the active client
     *
     * @return LLMClientInterface|null
     */
    public function getLLMClient()
    {
        return $this->active_client;
    }

    /**
     * Get client name
     *
     * @return string
     */
    public function getClientName(): string
    {
        $client = $this->getLLMClient();
        return $client ? $client::getClientName() : '';
    }

    /**
     * Validate configuration
     *
     * @return bool
     */
    public function validate(): bool
    {
        $client = $this->getLLMClient();
        return $client ? $client->validate() : false;
    }

    /**
     * Get the default LLM client
     *
     * @return LLMClientInterface|null
     */
    public static function getDefaultClient()
    {
        return LLMClient::config()->get(name: 'default_client');
    }

    public function __call($method, $arguments)
    {
        $client = $this->getLLMClient();

        if (!$client) {
            throw new BadMethodCallException("No active LLM client configured");
        }

        if (!method_exists($client, $method)) {
            throw new BadMethodCallException(sprintf(
                "Method %s::%s does not exist",
                get_class($client),
                $method
            ));
        }

        if ($this->logger) {
            $this->logger->debug("Delegating {$method} to " . get_class($client));
        }

        return call_user_func_array([$client, $method], $arguments);
    }
}
