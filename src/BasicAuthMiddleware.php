<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class BasicAuthMiddleware
{
    /**
     * @param string|callable $usersOrVerifier Either an associative array of ['username' => 'password'], a single username/password pair, or a custom callable validator.
     * @param string $realm The authentication realm displayed by the browser prompt.
     */
    public function __construct(
        private mixed $usersOrVerifier,
        private string $realm = 'Protected Area'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $authHeader = $request->header('Authorization');

        if (empty($authHeader) || !str_starts_with($authHeader, 'Basic ')) {
            return $this->unauthorized($response);
        }

        $token = substr($authHeader, 6);
        $decoded = base64_decode($token, true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return $this->unauthorized($response);
        }

        [$username, $password] = explode(':', $decoded, 2);

        // Validate credentials based on the type of validator provided
        if (!$this->validateCredentials($username, $password)) {
            return $this->unauthorized($response);
        }

        // Store the authenticated username in request attributes for controllers to use
        $request->setAttribute('auth_user', $username);

        return $next($request, $response);
    }

    private function validateCredentials(string $username, string $password): bool
    {
        // If a custom callable function was passed (e.g. database check)
        if (is_callable($this->usersOrVerifier)) {
            return ($this->usersOrVerifier)($username, $password);
        }

        // If an associative array of multiple users was passed: ['admin' => 'secret123']
        if (is_array($this->usersOrVerifier)) {
            if (isset($this->usersOrVerifier[$username])) {
                // Use secure timing-safe comparison to prevent timing attacks
                return hash_equals($this->usersOrVerifier[$username], $password);
            }
            return false;
        }

        return false;
    }

    private function unauthorized(Response $response): Response
    {
        $response->header('WWW-Authenticate', sprintf('Basic realm="%s"', $this->realm));

        return $response->status(401)->json([
            'error' => 'Unauthorized access.',
            'message' => 'Invalid or missing authentication credentials.'
        ]);
    }
}
