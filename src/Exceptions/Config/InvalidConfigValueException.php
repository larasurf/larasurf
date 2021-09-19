<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigValueException extends Exception
{
    /**
     * The key specified.
     *
     * @var string
     */
    public string $key;

    /**
     * The validation error messages.
     *
     * @var array
     */
    public array $messages;

    /**
     * InvalidConfigValueException constructor.
     * @param string $key
     * @param array $messages
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $key, array $messages, int $code = 0, Throwable $previous = null)
    {
        $this->key = $key;
        $this->messages = $messages;

        parent::__construct('Invalid LaraSurf configuration value', $code, $previous);
    }
}
