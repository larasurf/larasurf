<?php

namespace LaraSurf\LaraSurf\Exceptions\Config;

use Exception;
use Throwable;

class InvalidConfigKeyException extends Exception
{
    /**
     * The invalid key specified.
     *
     * @var string
     */
    public string $key;

    /**
     * InvalidConfigKeyException constructor.
     * @param string $key
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $key, int $code = 0, Throwable $previous = null)
    {
        $this->key = $key;

        parent::__construct('Invalid LaraSurf configuration key', $code, $previous);
    }
}
