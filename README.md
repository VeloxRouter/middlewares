# VeloxRouter Middlewares

Official middleware collection for the [VeloxRouter](https://github.com/VeloxRouter/router) ecosystem. Built for high performance, zero unnecessary dependencies, and full PHP 8.2+ compatibility.

## Installation

Install the package via Composer:

```bash
composer require veloxrouter/middlewares

```

## Available Middlewares

| Middleware | Description |
| --- | --- |
| **CorsMiddleware** | Handles Cross-Origin Resource Sharing (CORS) headers and OPTIONS preflight checks. |
| **JsonBodyMiddleware** | Ensures incoming payload requests (`POST`, `PUT`, `PATCH`) enforce `application/json`. |
| **RequestIdMiddleware** | Injects and tracks a unique `X-Request-ID` header into requests and responses. |
| **EtagMiddleware** | Generates HTTP ETags for response caching and handles `If-None-Match` checks. |
| **BasicAuthMiddleware** | Validates HTTP Basic Authentication using a custom closure validator. |
| **RateLimiterMiddleware** | Protects endpoints against abuse by limiting requests per IP within a time window. |
