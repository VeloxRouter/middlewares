<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class RequestLoggerMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
        private string $requestIdAttribute = 'request_id',
        private ?string $tenantAttribute = 'tenant_id'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $startTime = microtime(true);

        // Execute the rest of the pipeline and get the response
        $result = $next($request, $response);

        $duration = round((microtime(true) - $startTime) * 1000, 2); // duration in milliseconds
        $requestId = $request->getAttribute($this->requestIdAttribute) ?? 'N/A';
        
        // Retrieve tenant ID if configured and present in request attributes
        $tenantId = $this->tenantAttribute ? ($request->getAttribute($this->tenantAttribute) ?? 'N/A') : null;

        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'uri' => $request->uri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->ip() ?? 'unknown'
        ];

        if ($tenantId !== null && $this->tenantAttribute !== null) {
            $logData[$this->tenantAttribute] = $tenantId;
        }

        // Log request details using PSR-3 standard
        $this->logger->info('HTTP Request Handled', $logData);

        return $result;
    }
}
