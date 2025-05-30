<?php

namespace KhalsaJio\AI\Nexus\Util;

use KhalsaJio\AI\Nexus\LLMClient;
use KhalsaJio\AI\Nexus\Provider\DefaultStreamResponseHandler;

/**
 * Utility class for testing AI connection and functionality
 */
class TestUtil
{
    /**
     * Test connection to the LLM provider
     * 
     * @param bool $verbose Whether to output detailed information
     * @return bool True if connection is successful
     */
    public static function testConnection(bool $verbose = false): bool
    {
        try {
            $client = LLMClient::create();

            if ($verbose) {
                echo "Connected to provider: " . $client->getClientName() . "\n";
                echo "Using model: " . $client->getModel() . "\n";
            }

            // Simple test message
            $payload = [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => 'Respond with "Connection successful" if you can read this message.']
                ],
                'max_tokens' => 20
            ];

            $response = $client->chat($payload, 'chat/completions');

            if ($response['success'] !== true) {
                if ($verbose) {
                    echo "Connection error: " . ($response['error'] ?? 'Unknown error') . "\n";
                }
                return false;
            }

            if ($verbose) {
                echo "Response: " . $response['content'] . "\n";
                echo "Tokens used: " . json_encode($response['usage']) . "\n";
            }

            return true;
        } catch (\Exception $e) {
            if ($verbose) {
                echo "Connection error: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }
    
    /**
     * Test streaming connection
     * 
     * @param bool $verbose Whether to output detailed information
     * @return bool True if streaming is successful
     */
    public static function testStreaming(bool $verbose = false): bool
    {
        try {
            $client = LLMClient::create();
            $success = true;
            
            if ($verbose) {
                echo "Testing streaming with provider: " . $client->getClientName() . "\n";
            }

            // Create handler
            $handler = new DefaultStreamResponseHandler(
                function ($text) use ($verbose) {
                    if ($verbose) {
                        echo $text;
                    }
                },
                function ($content, $usage) use ($verbose) {
                    if ($verbose) {
                        echo "\n\nStreaming complete. Used tokens: " . json_encode($usage) . "\n";
                    }
                },
                function ($exception) use (&$success, $verbose) {
                    $success = false;
                    if ($verbose) {
                        echo "Streaming error: " . $exception->getMessage() . "\n";
                    }
                }
            );

            // Test payload
            $payload = [
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user', 'content' => 'Count from 1 to 5 slowly.']
                ],
                'max_tokens' => 50,
                'stream' => true
            ];

            $client->streamChat($payload, 'chat/completions', $handler);

            return $success;
        } catch (\Exception $e) {
            if ($verbose) {
                echo "Streaming error: " . $e->getMessage() . "\n";
            }
            return false;
        }
    }
    
    /**
     * Print diagnostic information
     * 
     * @return void
     */
    public static function printDiagnostics(): void
    {
        echo "SilverStripe AI Nexus - Diagnostics\n";
        echo "==================================\n";

        // PHP version
        echo "PHP Version: " . phpversion() . "\n";

        // Check for Guzzle
        echo "Guzzle installed: " . (class_exists('\GuzzleHttp\Client') ? 'Yes' : 'No') . "\n";

        // Check for SilverStripe
        echo "SilverStripe installed: " . (class_exists('\SilverStripe\Core\Manifest\ModuleManifest') ? 'Yes' : 'No') . "\n";

        // Check LLM client configuration
        try {
            $client = LLMClient::create();
            echo "Default LLM client: " . get_class($client->getLLMClient()) . "\n";
            echo "Provider: " . $client->getClientName() . "\n";
            echo "Model: " . $client->getModel() . "\n";

            // Test connection
            echo "\nTesting connection...\n";
            if (self::testConnection()) {
                echo "Connection test: PASSED\n";
            } else {
                echo "Connection test: FAILED\n";
            }
        } catch (\Exception $e) {
            echo "Error initializing LLM client: " . $e->getMessage() . "\n";
        }
    }
}
