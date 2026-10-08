<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class CacheMiddleware
{
    /**
     * @param int $ttl Cache time-to-live in seconds
     * @param string $prefix Prefix for keys stored in APCu
     */
    public function __construct(
        private readonly int $ttl = 60,
        private readonly string $prefix = 'velox_cache_'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if (strtoupper($request->method()) !== 'GET') {
            return $next($request, $response);
        }

        $apcuAvailable = function_exists('apcu_fetch') && ini_get('apc.enabled');

        $queryParams = $request->query();
        $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
        $cacheKey = $this->prefix . md5($request->uri() . $queryString);

        if ($apcuAvailable) {
            $cached = apcu_fetch($cacheKey, $success);
            if ($success && is_array($cached)) {
                return $response->json($cached['data'], $cached['status'])
                    ->header('X-Cache', 'HIT');
            }
        }

        /** @var Response $result */
        $result = $next($request, $response);

        if ($apcuAvailable && $result->status() === 200) {
            $body = $result->body();
            $decoded = json_decode((string)$body, true);

            if ($decoded !== null) {
                apcu_store($cacheKey, [
                    'data'   => $decoded,
                    'status' => $result->status()
                ], $this->ttl);
            }
        }

        return $result->header('X-Cache', 'MISS');
    }
}
