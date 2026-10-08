# VeloxRouter Middlewares

Official enterprise-grade middleware collection for the [VeloxRouter](https://github.com/VeloxRouter/router) ecosystem (PHP 8.2+). Built for extreme performance, zero heavy dependencies, and full pipeline composability inspired by Fiber and .NET architectures.

## Installation

Install the package via Composer:

```bash
composer require veloxrouter/middlewares

```

---

## 🛠️ Complete Suite of 20 Middlewares

The VeloxRouter middleware suite is categorized into security, resilience, data management, performance optimization, and observability layers:

### 1. Authentication & Security

| Middleware | Description |
| --- | --- |
| **ApiKeyMiddleware** | Validates external API keys safely using constant-time string comparisons (`hash_equals`). |
| **BasicAuthMiddleware** | Validates HTTP Basic Authentication credentials using a custom closure validator. |
| **RpcAuthMiddleware** | Secures internal microservice-to-microservice communication via constant-time token matching. |
| **CorsMiddleware** | Handles Cross-Origin Resource Sharing (CORS) headers and OPTIONS preflight requests. |
| **SecurityHeadersMiddleware** | Applies industry-standard OWASP security headers (HSTS, X-Frame-Options, XSS protection). |

### 2. Resilience & Traffic Protection

| Middleware | Description |
| --- | --- |
| **RateLimiterMiddleware** | Protects endpoints against brute-force and abuse by limiting requests per IP/user within a time window. |
| **TimeoutMiddleware** | Enforces hard execution time limits (`max_execution_time` + microtime tracking) returning HTTP 504. |
| **IdempotencyMiddleware** | Prevents duplicate processing of state-changing requests (`POST`, `PUT`, `PATCH`) using unique keys. |
| **ErrorHandlerMiddleware** | Catches exceptions globally and standardizes error responses in clean structured JSON format. |

### 3. Data & Transactions

| Middleware | Description |
| --- | --- |
| **ValidationMiddleware** | Validates incoming payload data against defined rules before reaching business controllers. |
| **JsonBodyMiddleware** | Ensures incoming request payloads (`POST`, `PUT`, `PATCH`) strictly enforce `application/json`. |
| **TransactionMiddleware** | Wraps the HTTP request lifecycle inside atomic database transactions (`begin`, `commit`/`rollback`). |

### 4. Performance & Optimization

| Middleware | Description |
| --- | --- |
| **CacheMiddleware** | Caches full responses or fragments using fast backend drivers (e.g., APCu) to reduce database load. |
| **GzipMiddleware** | Compresses outgoing response payloads using Gzip to optimize bandwidth consumption. |
| **EtagMiddleware** | Generates HTTP ETags for response validation and handles conditional `If-None-Match` checks. |

### 5. Observability & Utilities

| Middleware | Description |
| --- | --- |
| **RequestIdMiddleware** | Injects and tracks a unique `X-Request-ID` header into incoming requests and outgoing responses. |
| **RequestLoggerMiddleware** | Provides clean, PSR-3 compliant request and response logging for complete system observability. |
| **AuditLogMiddleware** | Records sensitive audit trails with context resolvers for financial and compliance tracking. |
| **I18nMiddleware** | Extracts and injects localization and language context into request headers and attributes. |
| **HealthCheckMiddleware** | Cloud-native health check endpoint provider (`/health`, `/health/live`, `/health/ready`) for K8s/Docker. |

---

## 🚀 Usage Examples

### 1. Global Pipeline Configuration

Ideal for system-wide concerns like request tracing, security headers, and error handling:

```php
use VeloxRouter\Router\Router;
use VeloxRouter\Middlewares\RequestIdMiddleware;
use VeloxRouter\Middlewares\SecurityHeadersMiddleware;
use VeloxRouter\Middlewares\RequestLoggerMiddleware;

$router = new Router();

$router->addGlobalMiddleware(new RequestIdMiddleware());$router->addGlobalMiddleware(new SecurityHeadersMiddleware());
$router->addGlobalMiddleware(new RequestLoggerMiddleware($logger));

```

### 2. Route-Specific Pipeline (Transactional API Endpoint)

Ideal for data-mutating routes requiring payload validation, traffic control, and automatic transaction safety:

```php
use VeloxRouter\Middlewares\RateLimiterMiddleware;
use VeloxRouter\Middlewares\TimeoutMiddleware;
use VeloxRouter\Middlewares\JsonBodyMiddleware;
use VeloxRouter\Middlewares\ValidationMiddleware;
use VeloxRouter\Middlewares\TransactionMiddleware;

$router->post('/api/v1/orders', [OrderController::class, 'store'], [
    new RateLimiterMiddleware(maxAttempts: 20, decaySeconds: 60),
    new TimeoutMiddleware(10),
    new JsonBodyMiddleware(),
    new ValidationMiddleware($orderRules),
    new TransactionMiddleware($dbManager)
]);

```

---

## License

The VeloxRouter Middlewares package is open-source software licensed under the [MIT license](https://www.google.com/search?q=LICENSE).

