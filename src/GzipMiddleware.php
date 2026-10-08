<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class GzipMiddleware
{
    /**
     * @param int $minBytes Minimum body size in bytes required to trigger compression
     * @param int $level Gzip compression level (1 to 9)
     */
    public function __construct(
        private readonly int $minBytes = 1024,
        private readonly int $level = 6
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        /** @var Response $result */
        $result = $next($request, $response);

        // Do not compress if the response type is not JSON or text
        $type = $result->header('Content-Type') ?? '';
        if (!str_contains($type, 'json') && !str_contains($type, 'text')) {
            return $result;
        }

        // Do not compress if already encoded or if the body is smaller than the minimum threshold
        $body = (string)$result->body();
        if (strlen($body) < $this->minBytes) {
            return $result;
        }
        if ($result->header('Content-Encoding')) {
            return $result;
        }

        // Only compress if the client explicitly accepts gzip encoding
        $accept = $request->header('Accept-Encoding') ?? '';
        if (!str_contains($accept, 'gzip')) {
            return $result;
        }

        $gz = gzencode($body, $this->level);

        // If compression didn't actually reduce the size, bypass it
        if ($gz === false || strlen($gz) >= strlen($body)) {
            return $result;
        }

        return $result
            ->header('Content-Encoding', 'gzip')
            ->header('Content-Length', (string) strlen($gz))
            ->header('Vary', 'Accept-Encoding')
            ->body($gz);
    }
}
