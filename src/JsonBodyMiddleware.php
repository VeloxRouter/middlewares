<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class JsonBodyMiddleware
{
    public function __construct(
        private bool $assoc = true,
        private int $depth = 512,
        private int $flags = JSON_THROW_ON_ERROR
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Check if the request contains a JSON content type
        $contentType = $request->header('Content-Type') ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = $request->rawBody();

            // If the body is not empty, attempt to parse the JSON payload
            if (!empty($rawBody)) {
                try {
                    $data = json_decode($rawBody, $this->assoc, $this->depth, $this->flags);
                    
                    // Inject the parsed data into the request object
                    $request->setParsedBody($data);
                } catch (\JsonException $e) {
                    // Return a 400 Bad Request if the JSON syntax is invalid
                    return $response->status(400)->json([
                        'error' => 'Invalid JSON payload provided.',
                        'message' => $e->getMessage()
                    ]);
                }
            }
        }

        return $next($request, $response);
    }
}
