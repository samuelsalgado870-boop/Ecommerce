<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessConflict extends HttpException implements ShouldntReport
{
    public function __construct(string $message)
    {
        parent::__construct(409, $message);
    }
}
