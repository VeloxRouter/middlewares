<?php

declare(strict_types=1);

namespace VeloxRouter\Middlewares;

use Psr\Log\LoggerInterface;
use VeloxRouter\Http\Request;
use VeloxRouter\Http\Response;

class I18nMiddleware
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private string $defaultLanguage = 'en',
        private string $languageAttribute = 'lang'
    ) {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        // Read the Accept-Language header from the HTTP request
        $headerLang = $request->header('Accept-Language');

        $lang = $this->defaultLanguage;

        if (!empty($headerLang)) {
            // Extract the first 2 characters (e.g., 'pt-BR' or 'pt-PT' becomes 'pt')
            $lang = trim(substr($headerLang, 0, 2));
        }

        // Set the language in the Request attributes (accessible to ErrorMiddleware or Controllers)
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
