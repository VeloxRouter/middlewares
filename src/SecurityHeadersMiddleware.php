<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;
use VeloxRouter\Middleware\MiddlewareInterface;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly array $customPolicies = [],
        private readonly bool $forceHstsOnlyOverHttps = true
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Essential Hardening (OWASP Standard)
        $response->setHeader('X-Frame-Options', $this->customPolicies['X-Frame-Options'] ?? 'DENY');
        $response->setHeader('X-Content-Type-Options', $this->customPolicies['X-Content-Type-Options'] ?? 'nosniff');
        $response->setHeader('Referrer-Policy', $this->customPolicies['Referrer-Policy'] ?? 'strict-origin-when-cross-origin');
        $response->setHeader('X-XSS-Protection', $this->customPolicies['X-XSS-Protection'] ?? '1; mode=block');
        $response->setHeader('Permissions-Policy', $this->customPolicies['Permissions-Policy'] ?? 'geolocation=(), microphone=(), camera=()');
        $response->setHeader('Content-Security-Policy', $this->customPolicies['Content-Security-Policy'] ?? "default-src 'none'; frame-ancestors 'none';");

        // Advanced Origin Isolation (Optional / Big Tech standard)
        if (isset($this->customPolicies['Cross-Origin-Opener-Policy'])) {
            $response->setHeader('Cross-Origin-Opener-Policy', $this->customPolicies['Cross-Origin-Opener-Policy']);
        }
        
        if (isset($this->customPolicies['Cross-Origin-Resource-Policy'])) {
            $response->setHeader('Cross-Origin-Resource-Policy', $this->customPolicies['Cross-Origin-Resource-Policy']);
        }

        // Smart HSTS (Applied only if HTTPS is active)
        if ($this->shouldApplyHsts($request)) {
            $hstsValue = $this->customPolicies['Strict-Transport-Security'] ?? 'max-age=31536000; includeSubDomains; preload';
            $response->setHeader('Strict-Transport-Security', $hstsValue);
        }

        return $response;
    }

    private function shouldApplyHsts(Request $request): bool
    {
        if (!$this->forceHstsOnlyOverHttps) {
            return true;
        }

        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
}
