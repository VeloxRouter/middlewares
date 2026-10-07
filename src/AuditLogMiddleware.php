<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class AuditLogMiddleware
{
    /**
     * @param LoggerInterface|null $logger The application logger (PSR-3)
     * @param string|null $logPath Fallback direct file path if no logger is provided
     * @param array<string> $methods HTTP methods to audit
     * @param array<string> $onlyPaths Whitelist of URI patterns
     * @param array<string> $exceptPaths Blacklist of URI patterns
     * @param callable|null $contextResolver Callback to extract extra context (tenant, user, etc.) from the request
     */
    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $logPath = null,
        private readonly array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'],
        private readonly array $onlyPaths = [],
        private readonly array $exceptPaths = ['/login', '/health'],
        private readonly $contextResolver = null
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        /** @var Response $result */
        $result = $next($request, $response);

        if (!in_array(strtoupper($request->method()), $this->methods, true)) {
            return $result;
        }

        $uri = $request->uri();

        if (!empty($this->onlyPaths) && !$this->match($uri, $this->onlyPaths)) {
            return $result;
        }

        if ($this->match($uri, $this->exceptPaths)) {
            return $result;
        }

        $this->recordAudit($request, $result);

        return $result;
    }

    private function match(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($uri, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function recordAudit(Request $request, Response $response): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'method'    => $request->method(),
            'uri'       => $request->uri(),
            'status'    => $response->status(),
        ];

        // Se foi fornecido um resolver, injeta dinamicamente o contexto do utilizador/tenant
        if (is_callable($this->contextResolver)) {
            $extraContext = call_user_func($this->contextResolver, $request);
            if (is_array($extraContext)) {
                $logData = array_merge($extraContext, $logData);
            }
        }

        $logData['payload'] = $request->all() ?? [];

        if ($this->logger !== null) {
            $this->logger->info('Financial Audit Event', $logData);
            return;
        }

        if ($this->logPath !== null) {
            $logDir = dirname($this->logPath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $line = json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
        }
    }
}
