<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class IdempotencyMiddleware
{
    /**
     * @param int $ttl Time-to-live for the cached response in seconds (default: 300s / 5 mins)
     * @param string $cachePrefix Custom prefix for cache keys to prevent collisions across services
     */
    public function __construct(
        private readonly int $ttl = 300,
        private readonly string $cachePrefix = 'velox_idempotency_'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Idempotency is only applicable to state-changing HTTP methods
        $method = strtoupper($request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request, $response);
        }

        // Retrieve the idempotency key from request headers
        $idempotencyKey = $request->header('X-Idempotency-Key');
        if (empty($idempotencyKey)) {
            return $next($request, $response);
        }

        $cacheKey = $this->cachePrefix . md5($idempotencyKey);

        // Check if a cached response already exists for this key
        if (function_exists('apcu_exists') && apcu_exists($cacheKey)) {
            $cached = apcu_fetch($cacheKey);
            if (is_array($cached)) {
                return $response->status($cached['status'])->json($cached['body']);
            }
        }

        /** @var Response $result */
        $result = $next($request, $response);

        // Cache successful responses (HTTP < 400) to prevent replay of errors
        if ($result->status() < 400 && function_exists('apcu_store')) {
            apcu_store($cacheKey, [
                'status' => $result->status(),
                'body'   => $result->body()
            ], $this->ttl);
        }

        return $result;
    }
}
