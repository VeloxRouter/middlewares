<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use Throwable;
use VeloxRouter\Exceptions\HttpException;
use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class ErrorMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'production',
        private string $requestIdAttribute = 'request_id',
        private ?string $tenantAttribute = 'tenant_id'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        try {
            return $next($request, $response);
        } catch (Throwable $e) {
            return $this->handleException($request, $response, $e);
        }
    }

    private function handleException(Request $request, Response $response, Throwable $e): Response
    {
        // Extrai os atributos usando exatamente os nomes configurados
        $requestId = $request->getAttribute($this->requestIdAttribute) ?? 'N/A';
        $tenantId = $this->tenantAttribute ? ($request->getAttribute($this->tenantAttribute) ?? 'N/A') : null;

        $statusCode = 500;
        $title = 'Internal Server Error';
        $detail = 'An unexpected error occurred';

        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $title = $e->getErrorTitle();
            $detail = $e->getMessage();
        } else {
            if ($this->environment !== 'production') {
                $detail = $e->getMessage();
            }
        }

        if ($this->environment !== 'production' || $statusCode >= 500) {
            $logData = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                $this->requestIdAttribute => $requestId,
                'path' => $request->path(),
                'status_code' => $statusCode,
            ];

            if ($tenantId !== null && $this->tenantAttribute !== null) {
                $logData[$this->tenantAttribute] = $tenantId;
            }

            $this->logger->error($title, $logData);
        }

        $errorResponse = [
            'status' => $statusCode,
            'title' => $title,
            'detail' => $detail,
            'path' => $request->path(),
        ];

        return $response->status($statusCode)->json($errorResponse);
    }
}
