<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Exceptions\InternalServerErrorHttpException;
use VeloxRouter\Exceptions\UnauthorizedHttpException;
use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class RpcAuthMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
        private string $internalKey,
        private string $authHeaderName = 'X-Internal-Key'
    ) {
        if (empty($this->internalKey)) {
            $this->logger->error("RPC_INTERNAL_KEY is not set. RPC communication will be insecure or fail.");
        }
    }

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if (empty($this->internalKey)) {
            $this->logger->error("RPC Auth misconfigured: server-side API key is empty");
            throw new InternalServerErrorHttpException("RPC authentication is misconfigured on the server");
        }

        $clientKey = $request->header($this->authHeaderName);

        if (empty($clientKey)) {
            $this->logger->warning("Missing RPC Internal Key", [
                'ip' => $request->ip() ?? 'unknown',
                'path' => $request->path()
            ]);
            throw new UnauthorizedHttpException("Missing internal API key for RPC communication");
        }

        if (!hash_equals($this->internalKey, $clientKey)) {
            $prefix = mb_strlen($clientKey) > 4 ? mb_substr($clientKey, 0, 4) . '...' : $clientKey;

            $this->logger->error("Invalid RPC Internal Key attempt", [
                'ip' => $request->ip() ?? 'unknown',
                'header' => $this->authHeaderName,
                'key_prefix' => $prefix,
                'path' => $request->path()
            ]);

            throw new UnauthorizedHttpException("Invalid internal API key");
        }

        return $next($request, $response);
    }
}
