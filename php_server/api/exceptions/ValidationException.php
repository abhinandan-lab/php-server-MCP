<?php

namespace App\Exceptions;

/**
 * Exception for validation errors
 */
class ValidationException extends BaseException
{
    protected int $httpStatusCode = 422;
    protected string $errorType = 'validation_error';
    
    private array $validationErrors = [];

    public function __construct(string $message, array $validationErrors = [], int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function toArray(): array
    {
        $data = parent::toArray();
        
        if (!empty($this->validationErrors)) {
            $data['validation_errors'] = $this->validationErrors;
        }

        return $data;
    }
}
