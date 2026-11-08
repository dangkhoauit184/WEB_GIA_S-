<?php
namespace App\Shared;

final class ApiException extends \RuntimeException
{
    public function __construct(public int $status, string $message, public string $errorCode = 'VALIDATION_ERROR')
    {
        parent::__construct($message);
    }
}
