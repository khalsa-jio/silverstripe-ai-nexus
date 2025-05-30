<?php

namespace KhalsaJio\AI\Nexus\Util;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Cache\CacheFactory;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Cache manager for LLM responses to reduce API calls and manage rate limits
 */
class CacheManager
{
    /**
     * Cache namespace
     */
    private const CACHE_KEY = 'LLMResponseCache';

    /**
     * Default TTL for cached responses in seconds (1 hour)
     */
    private const DEFAULT_TTL = 3600;
    
    /**
     * @config
     * @var int Default TTL value from configuration
     */
    private static $default_ttl = 3600;
    
    /**
     * @config
     * @var bool Whether caching is globally enabled
     */
    private static $enable_caching = true;

    /**
     * @config
     * @var array Provider-specific caching settings
     * @example
     * ```yml
     * provider_settings:
     *   openai:
     *     enable_caching: true
     *     ttl: 7200
     *   claude:
     *     enable_caching: false
     * ```
     */
    private static $provider_settings = [];

    /**
     * @config
     * @var array Endpoint-specific caching settings
     * @example
     * ```yml
     * endpoint_settings:
     *   'chat/completions':
     *     enable_caching: true
     *     ttl: 3600
     * ```
     */
    private static $endpoint_settings = [];
    
    /**
     * @config
     * @var bool Whether to track cache statistics
     */
    private static $enable_statistics = false;
    
    /**
     * @config
     * @var string Cache statistics key
     */
    private static $statistics_key = 'LLMCacheStats';
    
    /**
     * @config
     * @var int Maximum age for cache items in seconds (default 30 days)
     */
    private static $max_cache_age = 2592000;

    /**
     * Get the cache instance
     * 
     * @return \Psr\SimpleCache\CacheInterface
     */
    private static function getCache()
    {
        $factory = Injector::inst()->get(CacheFactory::class);
        return $factory->create(self::CACHE_KEY);
    }

    /**
     * Get the statistics cache instance
     * 
     * @return \Psr\SimpleCache\CacheInterface
     */
    private static function getStatsCache()
    {
        $factory = Injector::inst()->get(CacheFactory::class);
        $statsKey = Config::inst()->get(self::class, 'statistics_key') ?? self::CACHE_KEY . '_Stats';
        return $factory->create($statsKey);
    }

    /**
     * Generate a cache key for a specific request
     * 
     * @param array $payload Request payload
     * @param string $endpoint API endpoint
     * @param string $provider Provider name
     * @return string Cache key
     */
    private static function generateCacheKey(array $payload, string $endpoint, string $provider): string
    {
        // Remove any streaming flags as they don't affect content
        $normalizedPayload = $payload;
        unset($normalizedPayload['stream']);
        
        // Sort to ensure consistent keys regardless of array order
        if (isset($normalizedPayload['messages'])) {
            usort($normalizedPayload['messages'], function ($a, $b) {
                return strcmp($a['role'] . $a['content'], $b['role'] . $b['content']);
            });
        }
        
        // Create a stable string representation of the request
        $key = $provider . '_' . $endpoint . '_' . md5(json_encode($normalizedPayload));
        return $key;
    }

    /**
     * Cache a response
     * 
     * @param array $payload Original request payload
     * @param string $endpoint API endpoint
     * @param string $provider Provider name
     * @param mixed $response Response to cache
     * @param int|null $ttl Time-to-live in seconds
     * @return bool Success
     */
    public static function cacheResponse(
        array $payload, 
        string $endpoint, 
        string $provider, 
        $response, 
        ?int $ttl = null
    ): bool {
        // Check if caching is enabled
        if (!self::isCachingEnabled($provider, $endpoint)) {
            return false;
        }

        $key = self::generateCacheKey($payload, $endpoint, $provider);
        // Use provider or endpoint specific TTL if available
        $ttl = $ttl ?? self::getTTL($provider, $endpoint);
        
        try {
            $cache = self::getCache();
            $result = $cache->set($key, [
                'response' => $response,
                'created' => time(),
                'payload' => $payload,
                'provider' => $provider,
                'endpoint' => $endpoint
            ], $ttl);
            
            // Update statistics if enabled
            if (self::isStatisticsEnabled()) {
                self::updateCacheStats('hit', $provider, $endpoint);
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get a cached response if available
     * 
     * @param array $payload Request payload
     * @param string $endpoint API endpoint
     * @param string $provider Provider name
     * @return mixed|null Cached response or null
     */
    public static function getCachedResponse(array $payload, string $endpoint, string $provider)
    {
        if (!self::isCachingEnabled($provider, $endpoint)) {
            return null;
        }

        $key = self::generateCacheKey($payload, $endpoint, $provider);

        try {
            $cache = self::getCache();
            if ($cache->has($key)) {
                $cacheData = $cache->get($key);
                
                if (is_array($cacheData) && isset($cacheData['response'])) {
                    if (self::isStatisticsEnabled()) {
                        self::updateCacheStats('hit', $provider, $endpoint);
                    }
                    return $cacheData['response'];
                }

                return $cacheData;
            }

            // Record cache miss
            if (self::isStatisticsEnabled()) {
                self::updateCacheStats('miss', $provider, $endpoint);
            }
        } catch (\Exception $e) {
            // Fail silently
            if (self::isStatisticsEnabled()) {
                self::updateCacheStats('miss', $provider, $endpoint);
            }

            return null;
        }

        return null;
    }

    /**
     * Clear all cached responses
     * 
     * @return bool Success
     */
    public static function clearCache(): bool
    {
        try {
            $cache = self::getCache();
            $result = $cache->clear();

            if (self::isStatisticsEnabled()) {
                $statsCache = self::getStatsCache();
                $statsCache->set('last_clear', time());
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if a response is cached
     * 
     * @param array $payload Request payload
     * @param string $endpoint API endpoint
     * @param string $provider Provider name
     * @return bool True if cached
     */
    public static function isCached(array $payload, string $endpoint, string $provider): bool
    {
        if (!self::isCachingEnabled($provider, $endpoint)) {
            return false;
        }

        $key = self::generateCacheKey($payload, $endpoint, $provider);

        try {
            $cache = self::getCache();
            return $cache->has($key);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if caching is enabled in configuration
     * Takes into account provider and endpoint specific settings
     *
     * @param string|null $provider Provider name (optional)
     * @param string|null $endpoint API endpoint (optional)
     * @return bool
     */
    public static function isCachingEnabled(?string $provider = null, ?string $endpoint = null): bool
    {
        // First check global setting
        $globalSetting = Config::inst()->get(self::class, 'enable_caching') !== false;

        if (!$globalSetting) {
            return false;
        }

        // If no provider or endpoint specified, use global setting
        if ($provider === null && $endpoint === null) {
            return $globalSetting;
        }

        // Check provider-specific settings
        if ($provider !== null) {
            $providerSettings = Config::inst()->get(self::class, 'provider_settings') ?? [];
            $providerKey = strtolower($provider);
            
            if (isset($providerSettings[$providerKey]) && 
                isset($providerSettings[$providerKey]['enable_caching'])) {
                if ($providerSettings[$providerKey]['enable_caching'] === false) {
                    return false;
                }
            }
        }

        // Check endpoint-specific settings
        if ($endpoint !== null) {
            $endpointSettings = Config::inst()->get(self::class, 'endpoint_settings') ?? [];
            
            if (isset($endpointSettings[$endpoint]) && 
                isset($endpointSettings[$endpoint]['enable_caching'])) {
                if ($endpointSettings[$endpoint]['enable_caching'] === false) {
                    return false;
                }
            }
        }

        // If we've made it here, caching is enabled
        return true;
    }
    
    /**
     * Get the TTL for a specific provider and endpoint
     * 
     * @param string|null $provider Provider name
     * @param string|null $endpoint API endpoint
     * @return int TTL in seconds
     */
    public static function getTTL(?string $provider = null, ?string $endpoint = null): int
    {
        // Start with default TTL
        $ttl = Config::inst()->get(self::class, 'default_ttl') ?? self::DEFAULT_TTL;

        // Check provider-specific settings
        if ($provider !== null) {
            $providerSettings = Config::inst()->get(self::class, 'provider_settings') ?? [];
            $providerKey = strtolower($provider);
            
            if (isset($providerSettings[$providerKey]) && 
                isset($providerSettings[$providerKey]['ttl'])) {
                $ttl = $providerSettings[$providerKey]['ttl'];
            }
        }

        // Check endpoint-specific settings (overrides provider settings)
        if ($endpoint !== null) {
            $endpointSettings = Config::inst()->get(self::class, 'endpoint_settings') ?? [];
            
            if (isset($endpointSettings[$endpoint]) && 
                isset($endpointSettings[$endpoint]['ttl'])) {
                $ttl = $endpointSettings[$endpoint]['ttl'];
            }
        }
        
        return (int)$ttl;
    }

    /**
     * Check if statistics tracking is enabled
     * 
     * @return bool
     */
    public static function isStatisticsEnabled(): bool
    {
        return Config::inst()->get(self::class, 'enable_statistics') === true;
    }

    /**
     * Update cache statistics
     * 
     * @param string $type Type of stat (hit, miss)
     * @param string|null $provider Provider name
     * @param string|null $endpoint API endpoint
     * @return void
     */
    private static function updateCacheStats(string $type, ?string $provider = null, ?string $endpoint = null): void
    {
        if (!self::isStatisticsEnabled()) {
            return;
        }

        try {
            $statsCache = self::getStatsCache();
            $stats = $statsCache->get('stats') ?? [
                'hits' => 0,
                'misses' => 0,
                'providers' => [],
                'endpoints' => []
            ];

            // Update global stats
            if ($type === 'hit') {
                $stats['hits']++;
            } elseif ($type === 'miss') {
                $stats['misses']++;
            }
            
            // Update provider stats
            if ($provider !== null) {
                $providerKey = strtolower($provider);
                if (!isset($stats['providers'][$providerKey])) {
                    $stats['providers'][$providerKey] = ['hits' => 0, 'misses' => 0];
                }

                if ($type === 'hit') {
                    $stats['providers'][$providerKey]['hits']++;
                } elseif ($type === 'miss') {
                    $stats['providers'][$providerKey]['misses']++;
                }
            }

            // Update endpoint stats
            if ($endpoint !== null) {
                if (!isset($stats['endpoints'][$endpoint])) {
                    $stats['endpoints'][$endpoint] = ['hits' => 0, 'misses' => 0];
                }

                if ($type === 'hit') {
                    $stats['endpoints'][$endpoint]['hits']++;
                } elseif ($type === 'miss') {
                    $stats['endpoints'][$endpoint]['misses']++;
                }
            }

            // Save updated stats
            $statsCache->set('stats', $stats);
        } catch (\Exception $e) {
            // Fail silently
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function getStatistics(): array
    {
        if (!self::isStatisticsEnabled()) {
            return ['enabled' => false];
        }
        
        try {
            $statsCache = self::getStatsCache();
            $stats = $statsCache->get('stats') ?? [
                'hits' => 0,
                'misses' => 0,
                'providers' => [],
                'endpoints' => []
            ];
            
            // Calculate hit rate
            $total = $stats['hits'] + $stats['misses'];
            $hitRate = $total > 0 ? ($stats['hits'] / $total) * 100 : 0;
            
            return [
                'enabled' => true,
                'hits' => $stats['hits'],
                'misses' => $stats['misses'],
                'total' => $total,
                'hit_rate' => round($hitRate, 2),
                'providers' => $stats['providers'],
                'endpoints' => $stats['endpoints'],
                'last_clear' => $statsCache->get('last_clear')
            ];
        } catch (\Exception $e) {
            return ['enabled' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reset cache statistics
     * 
     * @return bool Success
     */
    public static function resetStatistics(): bool
    {
        if (!self::isStatisticsEnabled()) {
            return false;
        }

        try {
            $statsCache = self::getStatsCache();
            return $statsCache->delete('stats');
        } catch (\Exception $e) {
            return false;
        }
    }
}
