<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigValueException extends Exception
{
    public string $key;
    public array $messages;

    public function __construct(string $key, array $messages, int $code = 0, Throwable $previous = null)
    {
        $this->key = $key;
        $this->messages = $messages;

        parent::__construct('Invalid LaraSurf configuration value', $code, $previous);
    }
}
