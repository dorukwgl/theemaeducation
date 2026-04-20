<?php

namespace EMA\Middleware;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Core\Response;

class ValidationMiddleware
{
    private array $rules;
    private ?Validator $validator = null;

    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    public function handle($next)
    {
        if (empty($this->rules)) {
            return $next();
        }

        // Get request data
        $request = new \EMA\Core\Request();
        $data = $request->allInput();

        // Create and run validator
        $this->validator = Validator::make($data, $this->rules);

        if (!$this->validator->validate()) {
            Logger::info('Validation failed', [
                'errors' => $this->validator->getErrors(),
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
            ]);

            $response = new Response();
            $response->validationError(
                $this->validator->getErrors(),
                'Validation failed'
            );
        }

        Logger::debug('Validation passed', [
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);

        return $next();
    }

    public function getValidator(): ?Validator
    {
        return $this->validator;
    }

    public function getErrors(): array
    {
        return $this->validator ? $this->validator->getErrors() : [];
    }

    public function hasErrors(): bool
    {
        return $this->validator ? $this->validator->hasErrors() : false;
    }

    public function getFirstError(): ?string
    {
        return $this->validator ? $this->validator->getFirstError() : null;
    }
}