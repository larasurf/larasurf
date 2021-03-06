<?php

namespace LaraSurf\LaraSurf\Exceptions\AwsClients;

use Exception;
use Throwable;

class InvalidArgumentException extends Exception
{
    /**
     * InvalidArgumentException constructor.
     * @param string $name
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $name, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Invalid value for argument '$name'", $code, $previous);
    }
}
