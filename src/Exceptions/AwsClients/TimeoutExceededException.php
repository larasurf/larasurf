<?php

namespace LaraSurf\LaraSurf\Exceptions\AwsClients;

use Exception;
use Throwable;

class TimeoutExceededException extends Exception
{
    public function __construct(int $timeout_seconds, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Failed to complete the operation within $timeout_seconds seconds", $code, $previous);
    }
}
