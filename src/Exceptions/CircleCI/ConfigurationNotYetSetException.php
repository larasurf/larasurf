<?php

namespace LaraSurf\LaraSurf\Exceptions\CircleCI;

use Exception;
use Throwable;

class ConfigurationNotYetSetException extends Exception
{
    public function __construct(int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Configuration has not yet been set', $code, $previous);
    }
}
