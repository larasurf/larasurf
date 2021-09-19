<?php

namespace LaraSurf\LaraSurf\Exceptions\Git;

use Exception;
use Throwable;

class ParsingGitConfigFailedException extends Exception
{
    /**
     * ParsingGitConfigFailedException constructor.
     * @param string $config_path
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(public string $config_path, int $code = 0, Throwable $previous = null)
    {
        parent::__construct("Failed to parse git config at: {$this->config_path}", $code, $previous);
    }
}
