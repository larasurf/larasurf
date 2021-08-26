<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects;

class PrefixListEntry extends DataTransferObject
{
    protected ?string $cidr;
    protected ?string $description;

    public function toArray()
    {
        return [
            'Cidr' => $this->cidr,
            'Description' => $this->description,
        ];
    }

    public function loadArray(array $data = null)
    {
        $this->cidr = $data['Cidr'] ?? null;
        $this->description = $data['Description'] ?? null;
    }

    public function getCidr(): ?string
    {
        return $this->cidr;
    }

    public function setCidr(string $cidr): static
    {
        $this->cidr = $cidr;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
