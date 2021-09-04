<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects;

class AccessKey extends DataTransferObject
{
    protected ?string $id;
    protected ?string $secret;

    public function toArray()
    {
        return [
            'AccessKeyId' => $this->id,
            'SecretAccessKey' => $this->secret,
        ];
    }

    public function loadArray(array $data = null)
    {
        $this->id = $data['AccessKeyId'] ?? null;
        $this->secret = $data['SecretAccessKey'] ?? null;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }
}
