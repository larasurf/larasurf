<?php

namespace LaraSurf\LaraSurf\Exceptions\AwsClients;

use Exception;
use Throwable;

class ExpectedArrayOfTypeException extends Exception
{
    public function __construct(string $type, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Expected array of type '$type'", $code, $previous);
    }
}
