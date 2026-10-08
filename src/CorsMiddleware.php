<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class CorsMiddleware
{
    /**
     * @param string|array<string> $allowedOrigins Single origin, '*', or an array of allowed origins (e.g. ['https://site1.com', 'https://site2.com'])
     * @param array<string>|string $allowedMethods Allowed HTTP methods (e.g. ['GET', 'POST'] or 'GET, POST, PUT')
     * @param array<string>|string $allowedHeaders Allowed request headers
     */
    public function __construct(
        private string|array $allowedOrigins = '*',
        private string|array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        private string|array $allowedHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-ID'],
        private bool $allowCredentials = false,
        private int $maxAge = 3600
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // 1. Dynamically resolve the Origin (Multi-site protection)
        $origin = $this->resolveOrigin($request);
        if ($origin !== null) {
            $response->header('Access-Control-Allow-Origin', $origin);
            
            // When credentials are used, the Vary header is highly recommended by the CORS spec
            if ($this->allowCredentials) {
                $response->header('Vary', 'Origin');
            }
        }

        // 2. Convert arrays to formatted strings if necessary
        $methods = is_array($this->allowedMethods) ? implode(', ', $this->allowedMethods) : $this->allowedMethods;
        $headers = is_array($this->allowedHeaders) ? implode(', ', $this->allowedHeaders) : $this->allowedHeaders;

        $response->header('Access-Control-Allow-Methods', $methods);
        $response->header('Access-Control-Allow-Headers', $headers);

        if ($this->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->maxAge > 0) {
            $response->header('Access-Control-Max-Age', (string) $this->maxAge);
        }

        // 3. Short-circuit OPTIONS preflight requests
        if (strtoupper($request->method()) === 'OPTIONS') {
            return $response->status(204);
        }

        return $next($request, $response);
    }

    private function resolveOrigin(Request $request): ?string
    {
        // If it's '*', allow everything
        if ($this->allowedOrigins === '*') {
            return '*';
        }

        // Get the origin sent by the browser
        $requestOrigin = $request->header('Origin');

        if (!$requestOrigin) {
            return null;
        }

        // If it's an array of allowed origins (whitelist)
        if (is_array($this->allowedOrigins)) {
            if (in_array($requestOrigin, $this->allowedOrigins, true)) {
                return $requestOrigin;
            }
            return null;
        }

        // If it's an exact string match (e.g. 'https://mywebsite.com')
        if ($this->allowedOrigins === $requestOrigin) {
            return $requestOrigin;
        }

        return null;
    }
}
