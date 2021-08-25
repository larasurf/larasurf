<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigException extends Exception
{
    public array $config;
    public array $messages;

    public function __construct(array $config, array $messages, int $code = 0, Throwable $previous = null)
    {
        $this->config = $config;
        $this->messages = $messages;

        parent::__construct('Invalid LaraSurf configuration', $code, $previous);
    }
}
