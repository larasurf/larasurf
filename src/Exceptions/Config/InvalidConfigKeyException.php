<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigKeyException extends Exception
{
    public string $key;

    public function __construct(string $key, int $code = 0, Throwable $previous = null)
    {
        $this->key = $key;

        parent::__construct('Invalid LaraSurf configuration key', $code, $previous);
    }
}
