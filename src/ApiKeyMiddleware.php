<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Exceptions\UnauthorizedHttpException;
use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class ApiKeyMiddleware
{
    private string $apiKey;

    public function __construct(
        string $apiKey,
        private ?LoggerInterface $logger = null,
        private string $headerName = 'X-API-Key'
    ) {
        $this->apiKey = trim($apiKey);
    }

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $clientKey = $request->header($this->headerName);

        if (empty($clientKey)) {
            if ($this->logger !== null) {
                $this->logger->warning("Unauthorized request. API Key missing", [
                    'ip' => $request->ip() ?? 'unknown',
                    'path' => $request->path()
                ]);
            }
            throw new UnauthorizedHttpException("Unauthorized. API Key missing");
        }

        if (!hash_equals($this->apiKey, $clientKey)) {
            if ($this->logger !== null) {
                $prefix = mb_strlen($clientKey) > 4 ? mb_substr($clientKey, 0, 4) . '...' : $clientKey;
                $this->logger->warning("Unauthorized request. Invalid API Key", [
                    'ip' => $request->ip() ?? 'unknown',
                    'path' => $request->path(),
                    'key_prefix' => $prefix
                ]);
            }
            throw new UnauthorizedHttpException("Unauthorized. Invalid API Key");
        }

        return $next($request, $response);
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = trim($apiKey);
    }
}
