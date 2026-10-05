<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Router\Http\Request;
use VeloxRouter\Router\Http\Response;

class I18nMiddleware
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private string $defaultLanguage = 'en',
        private string $languageAttribute = 'lang'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Lê o header Accept-Language do request HTTP
        $headerLang = $request->header('Accept-Language');

        $lang = $this->defaultLanguage;

        if (!empty($headerLang)) {
            // Pega os primeiros 2 caracteres (ex: 'pt-BR' ou 'pt-PT' vira 'pt') ou usa o código completo
            // Aqui podes optar por normalizar (ex: substr($headerLang, 0, 2)) ou aceitar o valor completo
            $lang = trim(substr($headerLang, 0, 2));
        }

        // Define o idioma nos atributos do Request (para o ErrorMiddleware ou Controllers acederem)
        $request->setAttribute($this->languageAttribute, $lang);

        if ($this->logger !== null) {
            $this->logger->debug("I18n language resolved", [
                'lang' => $lang,
                'accept_language_header' => $headerLang
            ]);
        }

        return $next($request, $response);
    }
}
