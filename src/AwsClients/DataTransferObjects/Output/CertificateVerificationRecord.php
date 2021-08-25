<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects\Output;

use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\DataTransferObject;

class CertificateVerificationRecord extends DataTransferObject
{
    protected ?string $name;
    protected ?string $value;

    public function toArray()
    {
        return [
            'Name' => $this->name,
            'Value' => $this->value,
        ];
    }

    public function loadArray(array $data = null)
    {
        $this->name = $data['Name'] ?? null;
        $this->value = $data['Value'] ?? null;
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function getValue(): string|null
    {
        return $this->value;
    }
}
