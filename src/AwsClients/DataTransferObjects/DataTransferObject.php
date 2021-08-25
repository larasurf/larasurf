<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects;

use Illuminate\Contracts\Support\Arrayable;

abstract class DataTransferObject implements Arrayable
{
    abstract public function loadArray(array $data = null);

    public function __construct(array $data = null)
    {
        $this->loadArray($data);
    }
}
