<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Middleware to automatically handle database transactions based on the HTTP lifecycle.
 */
class TransactionMiddleware
{
    private object $manager;

    /**
     * @param object $manager Database connection or transaction manager implementing begin, commit, rollback, inTransaction
     */
    public function __construct(object $manager)
    {
        // Duck Typing: ensures the object provides the required transaction methods
        $requiredMethods = ['begin', 'commit', 'rollback', 'inTransaction'];
        foreach ($requiredMethods as $method) {
            if (!method_exists($manager, $method)) {
                throw new InvalidArgumentException(
                    "The transaction manager object must implement the method: {$method}()"
                );
            }
        }

        $this->manager = $manager;
    }

    /**
     * Handle an incoming request and manage database transactions automatically.
     * 
     * Starts a transaction if not already nested, commits on success (HTTP < 400),
     * and rolls back on HTTP errors (>= 400) or unhandled exceptions.
     */
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $nested = $this->manager->inTransaction();

        if (!$nested) {
            $this->manager->begin();
        }

        try {
            /** @var Response $result */
            $result = $next($request, $response);

            if (!$nested) {
                // If response status indicates an HTTP error (400+), automatically rollback
                if ($result->status() >= 400) {
                    $this->manager->rollback();
                } else {
                    $this->manager->commit();
                }
            }

            return $result;
        } catch (Throwable $e) {
            if (!$nested && $this->manager->inTransaction()) {
                $this->manager->rollback();
            }
            throw $e;
        }
    }
}
