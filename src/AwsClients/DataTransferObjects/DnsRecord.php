<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects;

use LaraSurf\LaraSurf\Exceptions\AwsClients\InvalidArgumentException;

class DnsRecord extends DataTransferObject
{
    const TYPE_CNAME = 'CNAME';
    const TYPE_TXT = 'TXT';

    const TYPES = [
        self::TYPE_CNAME,
        self::TYPE_TXT,
    ];

    const TTL_DEFAULT = 300;

    protected ?string $name;
    protected ?string $value;
    protected ?string $type;
    protected int $ttl = self::TTL_DEFAULT;

    public function toArray()
    {
        return [
            'Name' => $this->name,
            'ResourceRecords' => [
                [
                    'Value' => $this->value,
                ],
            ],
            'TTL' => $this->ttl,
            'Type' => $this->type,
        ];
    }

    public function loadArray(array $data = null)
    {
        $this->name = $data['Name'] ?? null;
        $this->value = $data['ResourceRecords'][0]['Value'] ?? null;
        $this->ttl = $data['TTL'] ?? self::TTL_DEFAULT;
        $this->type = $data['Type'] ?? null;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value)
    {
        $this->value = $value;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type)
    {
        $this->validateType($type);

        $this->type = $type;

        return $this;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function setTtl(int $ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    protected function validateType(string $type)
    {
        if (!in_array($type, self::TYPES)) {
            throw new InvalidArgumentException('type');
        }
    }
}
