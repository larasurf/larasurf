<?php

namespace LaraSurf\LaraSurf\Exceptions\CircleCI;

use Exception;
use Illuminate\Http\Client\Response;
use Throwable;

class RequestFailedException extends Exception
{
    /**
     * RequestFailedException constructor.
     * @param Response $response
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(public Response $response, int $code = 0, Throwable $previous = null)
    {
        $message = 'Request to ' . $this->response->effectiveUri() . ' failed with status ' . $this->response->status();

        parent::__construct($message, $code, $previous);
    }
}
