<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class RateLimiterMiddleware
{
    public function __construct(
        private CacheInterface $cache,
        private ?LoggerInterface $logger = null,
        private int $maxAttempts = 60,
        private int $decaySeconds = 60
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($this->maxAttempts <= 0) {
            return $next($request, $response);
        }

        // Identify client uniquely (User ID if authenticated, otherwise IP)
        $keyIdentifier = $request->ip() ?? 'unknown_client';
        if ($userId = $request->getAttribute('auth_user')) {
            $keyIdentifier = (string) $userId;
        }

        $cacheKey = 'rate_limit:' . md5($keyIdentifier);

        try {
            // Increment the hit counter in cache
            $current = $this->cache->get($cacheKey, 0) + 1;
            $this->cache->set($cacheKey, $current, $this->decaySeconds);

            // Calculate remaining attempts
            $remaining = max(0, $this->maxAttempts - $current);

            // Set standard rate limit headers
            $response->header('X-RateLimit-Limit', (string) $this->maxAttempts);
            $response->header('X-RateLimit-Remaining', (string) $remaining);

            // Check if rate limit has been exceeded
            if ($current > $this->maxAttempts) {
                if ($this->logger) {
                    $this->logger->warning('Rate limit exceeded', [
                        'identifier' => $keyIdentifier,
                        'count' => $current
                    ]);
                }

                $response->header('Retry-After', (string) $this->decaySeconds);

                return $response->status(429)->json([
                    'error' => 'Too Many Requests',
                    'message' => 'Rate limit exceeded. Please try again later.'
                ]);
            }
        } catch (\Throwable $e) {
            // Fallback: if cache fails, log error and allow request to pass (fail-open)
            if ($this->logger) {
                $this->logger->error('Failed to process rate limit cache', [
                    'error' => $e->getMessage(),
                    'identifier' => $keyIdentifier
                ]);
            }
        }

        return $next($request, $response);
    }
}
