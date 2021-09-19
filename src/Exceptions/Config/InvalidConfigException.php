<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigException extends Exception
{
    /**
     * The current LaraSurf configuration.
     *
     * @var array
     */
    public array $config;

    /**
     * The validation error messages.
     *
     * @var array
     */
    public array $messages;

    /**
     * InvalidConfigException constructor.
     * @param array $config
     * @param array $messages
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(array $config, array $messages, int $code = 0, Throwable $previous = null)
    {
        $this->config = $config;
        $this->messages = $messages;

        parent::__construct('Invalid LaraSurf configuration', $code, $previous);
    }
}
