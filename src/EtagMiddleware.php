<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class ETagMiddleware
{
    /**
     * @param string $idAttribute Request attribute name for the resource ID (default: 'etag_id')
     * @param string $updatedAtAttribute Request attribute name for the last update timestamp (default: 'etag_updated_at')
     */
    public function __construct(
        private string $idAttribute = 'etag_id',
        private string $updatedAtAttribute = 'etag_updated_at'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Execute the rest of the pipeline first to get the response and let controllers set attributes
        $result = $next($request, $response);

        // Only process for GET requests and 200 OK statuses
        if (strtoupper($request->method()) !== 'GET' || $response->getStatusCode() !== 200) {
            return $result;
        }

        // Retrieve ETag domain metadata from request attributes (set by controllers)
        $id = $request->getAttribute($this->idAttribute);
        $updatedAt = $request->getAttribute($this->updatedAtAttribute);

        if (empty($id) || empty($updatedAt)) {
            return $result;
        }

        // Generate ETag based on entity ID and last update timestamp (mimicking Go helpers.GenerateETag)
        $rawEtag = sprintf('%s-%s', $id, is_object($updatedAt) && method_exists($updatedAt, 'getTimestamp') ? $updatedAt->getTimestamp() : (string) $updatedAt);
        $serverEtag = '"' . md5($rawEtag) . '"';

        // Set Vary header for proper proxy and cache handling
        $response->header('Vary', 'Accept-Encoding, If-None-Match');

        // Clean and compare with client's If-None-Match header
        $clientEtag = $this->cleanETag($request->header('If-None-Match') ?? '');

        if (!empty($clientEtag) && $clientEtag === $this->cleanETag($serverEtag)) {
            // Short-circuit: Return 304 Not Modified with empty body
            return $response->status(304)->body('');
        }

        // Attach the ETag header to the response
        $response->header('ETag', $serverEtag);

        return $result;
    }

    private function cleanETag(string $etag): string
    {
        // Strip out weak indicators (W/) and surrounding quotes for safe comparison
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            $etag = substr($etag, 2);
        }
        return trim($etag, '"');
    }
}
