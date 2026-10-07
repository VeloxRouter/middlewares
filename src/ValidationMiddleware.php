<?php
declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;
use VeloxRouter\Validator\Validator;

class ValidationMiddleware
{
    /**
     * @param array<string, string> $rules Regras de validação (ex: ['email' => 'required|email'])
     * @param array<string, string> $messages Mensagens customizadas opcionais
     */
    public function __construct(
        private readonly array $rules,
        private readonly array $messages = []
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // 1. Recolhe dados combinando query parameters e o corpo da requisição (JSON ou form)
        $queryData = $request->query() ?? [];
        $bodyData = $request->body() ?? [];
        $data = array_merge(
            is_array($queryData) ? $queryData : [], 
            is_array($bodyData) ? $bodyData : []
        );

        // 2. Deteta o idioma preferencial via header HTTP (default: pt)
        $acceptLang = $request->header('Accept-Language') ?? 'pt';

        // 3. Instancia e executa o motor de validação puro
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
