<?php

namespace KhalsaJio\AI\Nexus\Util;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;

/**
 * RetryManager provides utility methods for implementing retry logic with exponential backoff
 * for API calls to LLM services. This helps handle transient errors and rate limiting.
 */
class RetryManager
{
    use Configurable;
    use Injectable;
    use Extensible;

    /**
     * Maximum number of retries for API calls
     *
     * @config
     * @var int
     */
    private static $max_retries = 3;

    /**
     * Initial backoff time in milliseconds
     *
     * @config
     * @var int
     */
    private static $initial_backoff_ms = 1000;

    /**
     * Multiplier for exponential backoff
     *
     * @config
     * @var float
     */
    private static $backoff_multiplier = 2.0;

    /**
     * Error types that should trigger a retry
     *
     * @config
     * @var array
     */
    private static $retryable_errors = [
        'rate_limit',
        'timeout',
        'connection',
        'server_error',
        'unknown'
    ];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: Injector::inst()->get(LoggerInterface::class);
    }

    /**
     * Execute an API call with retry logic and exponential backoff
     *
     * @param callable $apiCall Function that makes the actual API call
     * @param int $maxRetries Maximum number of retry attempts (null to use config value)
     * @param int $initialBackoff Initial backoff time in milliseconds (null to use config value)
     * @param float $backoffMultiplier Multiplier for subsequent backoff times (null to use config value)
     * @param array $retryableErrors Array of error types/messages that should trigger a retry (null to use config value)
     * @return mixed Result of the successful API call
     * @throws Exception If all retry attempts fail
     */
    public function executeWithRetry(
        callable $apiCall,
        int $maxRetries = null,
        int $initialBackoff = null,
        float $backoffMultiplier = null,
        array $retryableErrors = null
    ) {
        $maxRetries = $maxRetries ?? $this->config()->get('max_retries');
        $initialBackoff = $initialBackoff ?? $this->config()->get('initial_backoff_ms');
        $backoffMultiplier = $backoffMultiplier ?? $this->config()->get('backoff_multiplier');
        $retryableErrors = $retryableErrors ?? $this->config()->get('retryable_errors');
        $attempts = 0;
        $backoffTime = $initialBackoff;
        $lastException = null;

        while ($attempts <= $maxRetries) {
            try {
                $attempts++;
                $result = $apiCall();

                if (is_array($result) && isset($result['error']) && !empty($result['error'])) {
                    $errorMessage = is_array($result['error']) ? ($result['error']['message'] ?? 'Unknown error') : $result['error'];
                    $errorType = is_array($result['error']) ? ($result['error']['type'] ?? 'unknown') : 'unknown';

                    if (!in_array($errorType, $retryableErrors)) {
                        throw new Exception("Error during API call: " . $errorMessage);
                    }

                    throw new Exception("Retryable error ({$errorType}): " . $errorMessage);
                }

                return $result;
            } catch (Exception $e) {
                $lastException = $e;
                $errorMessage = $e->getMessage();

                $shouldRetry = false;
                foreach ($retryableErrors as $errorType) {
                    if (stripos($errorMessage, $errorType) !== false) {
                        $shouldRetry = true;
                        break;
                    }
                }

                if (!$shouldRetry || $attempts > $maxRetries) {
                    throw $e;
                }

                $this->logger->warning(
                    "API call failed (attempt {$attempts}/{$maxRetries}), retrying in {$backoffTime}ms",
                    [
                        'error' => $errorMessage,
                        'backoff_ms' => $backoffTime
                    ]
                );

                // Wait for backoff period
                usleep($backoffTime * 1000); // Convert milliseconds to microseconds

                // Exponential backoff with jitter
                $backoffTime = $backoffTime * $backoffMultiplier * (0.5 + mt_rand(0, 1000) / 1000);
            }
        }

        // This should not be reached due to the exception in the loop,
        // but added as a safeguard
        throw new Exception(
            "API call failed after {$maxRetries} retries: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Static method for quick access to retry logic without instantiation
     *
     * @param callable $apiCall Function that makes the actual API call
     * @param int $maxRetries Maximum number of retry attempts
     * @param int $initialBackoff Initial backoff time in milliseconds
     * @param float $backoffMultiplier Multiplier for subsequent backoff times
     * @param array $retryableErrors Array of error types/messages that should trigger a retry
     * @return mixed Result of the successful API call
     * @throws Exception If all retry attempts fail
     */
    public static function execute(
        callable $apiCall,
        int $maxRetries = null,
        int $initialBackoff = null,
        float $backoffMultiplier = null,
        array $retryableErrors = null
    ) {
        $manager = Injector::inst()->get(RetryManager::class);
        return $manager->executeWithRetry(
            $apiCall,
            $maxRetries,
            $initialBackoff,
            $backoffMultiplier,
            $retryableErrors
        );
    }
}
