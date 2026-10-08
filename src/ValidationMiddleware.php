<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;
use VeloxRouter\Validator\Validator;

class ValidationMiddleware
{
    /**
     * @param array<string, string> $rules Validation rules (e.g., ['email' => 'required|email'])
     * @param array<string, string> $messages Optional custom messages
     */
    public function __construct(
        private readonly array $rules,
        private readonly array $messages = []
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Gather data by combining query parameters and request body (JSON or form)
        $queryData = $request->query() ?? [];
        $bodyData = $request->body() ?? [];
        $data = array_merge(
            is_array($queryData) ? $queryData : [], 
            is_array($bodyData) ? $bodyData : []
        );

        // Detect preferred language via HTTP header (default: en)
        $acceptLang = $request->header('Accept-Language') ?? 'en';

        // Instantiate and run the core validation engine
        $validator = new Validator($data, $this->rules, $this->messages, $acceptLang);

        if ($validator->fails()) {
            return $response->json([
                'code'    => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        return $next($request, $response);
    }
}
