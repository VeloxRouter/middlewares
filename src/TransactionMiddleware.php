<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;
use VeloxRouter\Contracts\TransactionManagerInterface;
use InvalidArgumentException;
use Throwable;

/**
 * Middleware to automatically handle database transactions based on the HTTP lifecycle.
 */
class TransactionMiddleware
{
    private TransactionManagerInterface $manager;

    /**
     * @param object|TransactionManagerInterface $manager Database connection or transaction manager
     */
    public function __construct(object $manager)
    {
        if ($manager instanceof TransactionManagerInterface) {
            $this->manager = $manager;
            return;
        }

        // Duck Typing: ensures the object provides the required transaction contract methods
        $requiredMethods = ['begin', 'commit', 'rollback', 'inTransaction'];
        foreach ($requiredMethods as $method) {
            if (!method_exists($manager, $method)) {
                throw new InvalidArgumentException(
                    "The transaction manager object must implement the method: {$method}()"
                );
            }
        }

        // Wrap non-implementing managers dynamically into the expected interface
        $this->manager = new class($manager) implements TransactionManagerInterface {
            public function __construct(private readonly object $target) {}

            public function begin(): void
            {
                $this->target->begin();
            }

            public function commit(): void
            {
                $this->target->commit();
            }

            public function rollback(): void
            {
                $this->target->rollback();
            }

            public function inTransaction(): bool
            {
                return $this->target->inTransaction();
            }
        };
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
