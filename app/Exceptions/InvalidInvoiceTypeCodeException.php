<?php

namespace App\Exceptions;

use Exception;

class InvalidInvoiceTypeCodeException extends Exception
{
    public function __construct(string $message = "Invalid invoice type code")
    {
        parent::__construct($message);
    }
}
