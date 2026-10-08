<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;
use Throwable;

class HealthCheckMiddleware
{
    private static float $startTime;

    /**
     * @param string $basePath Base path for health checks (e.g., '/health')
     * @param ?callable $dependencyChecker Callback to check external dependencies (DB, Redis, etc.)
     */
    public function __construct(
        private readonly string $basePath = '/health',
        private readonly ?callable $dependencyChecker = null
    ) {
        if (!isset(self::$startTime)) {
            self::$startTime = microtime(true);
        }
    }

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $uri = $request->uri();

        // 1. Liveness endpoint: /health/live
        if ($uri === $this->basePath . '/live') {
            return $response->status(200)->json([
                'status'    => 'ok',
                'timestamp' => date('c'),
            ]);
        }

        // 2. Readiness endpoint: /health/ready
        if ($uri === $this->basePath . '/ready') {
            $isHealthy = true;
            $components = [];

            if ($this->dependencyChecker !== null) {
                try {
                    $components = ($this->dependencyChecker)();
                    // Se algum componente retornar false ou estado down
                    if (is_array($components)) {
                        foreach ($components as $compStatus) {
                            if ($compStatus === false || (is_string($compStatus) && $compStatus !== 'UP')) {
                                $isHealthy = false;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $isHealthy = false;
                    $components['error'] = $e->getMessage();
                }
            }

            return $response->status($isHealthy ? 200 : 503)->json([
                'status'       => $isHealthy ? 'ready' : 'degraded',
                'dependencies' => $components,
            ]);
        }

        // 3. Full Health Check endpoint: /health
        if ($uri === $this->basePath) {
            $isHealthy = true;
            $components = [];

            if ($this->dependencyChecker !== null) {
                try {
                    $components = ($this->dependencyChecker)();
                } catch (Throwable $e) {
                    $isHealthy = false;
                    $components['error'] = $e->getMessage();
                }
            }

            $uptimeSeconds = microtime(true) - self::$startTime;

            return $response->status($isHealthy ? 200 : 503)->json([
                'status'       => $isHealthy ? 'healthy' : 'degraded',
                'timestamp'    => date('c'),
                'uptime'       => gmdate('H:i:s', (int) $uptimeSeconds),
                'dependencies' => $components,
            ]);
        }

        return $next($request, $response);
    }
}
