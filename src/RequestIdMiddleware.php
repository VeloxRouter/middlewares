<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class RequestIdMiddleware
{
    /**
     * @param string $headerName The HTTP header name (e.g. 'X-Request-ID' or 'X-Correlation-ID')
     * @param string $attributeName The request attribute key to access it later (e.g. 'request_id' or 'correlation_id')
     */
    public function __construct(
        private string $headerName = 'X-Request-ID',
        private string $attributeName = 'request_id'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Check if the request already has the ID (e.g. sent by an API Gateway or client)
        $requestId = $request->header($this->headerName);

        // If not present or empty, generate a secure UUID v4
        if (empty($requestId)) {
            $requestId = $this->generateUuidV4();
        }

        // Inject the ID into the request attributes dynamically
        $request->setAttribute($this->attributeName, $requestId);

        // Echo the ID back in the response headers for tracking/debugging
        $response->header($this->headerName, $requestId);

        // Continue the pipeline
        return $next($request, $response);
    }

    /**
     * Generate a cryptographically secure UUID v4 string without external dependencies.
     */
    private function generateUuidV4(): string
    {
        $data = random_bytes(16);
        
        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        
        // Set bits 6-7 to 10xx (RFC 4122 variant)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
