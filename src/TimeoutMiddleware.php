<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;
use Throwable;

class TimeoutMiddleware
{
    /**
     * @param int $seconds Maximum execution time allowed in seconds
     */
    public function __construct(private readonly int $seconds = 10) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $startTime = microtime(true);
        $previousMaxExecution = (int) ini_get('max_execution_time');
        
        // Set the native PHP execution time limit
        set_time_limit($this->seconds);

        try {
            // Execute the next middleware in the pipeline
            /** @var Response $result */
            $result = $next($request, $response);

            // Additional check for elapsed execution time
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $this->seconds) {
                return $response->status(504)->json([
                    'error' => 'Gateway Timeout',
                    'message' => 'The request took too long to process and timed out.'
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            // Check if the exception is due to a PHP execution timeout or time limit exceeded
            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $this->seconds || str_contains($e->getMessage(), 'Maximum execution time')) {
                return $response->status(504)->json([
                    'error' => 'Gateway Timeout',
                    'message' => 'The request exceeded the maximum allowed execution time.'
                ]);
            }
            
            throw $e;
        } finally {
            // Safely restore the original PHP time limit
            if ($previousMaxExecution > 0) {
                set_time_limit($previousMaxExecution);
            }
        }
    }
}
