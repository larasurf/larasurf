<?php

namespace LaraSurf\LaraSurf\AwsClients\DataTransferObjects\Output\Input;

use LaraSurf\LaraSurf\AwsClients\DataTransferObjects\Output\DataTransferObject;
use LaraSurf\LaraSurf\Exceptions\AwsClients\InvalidArgumentException;

class DnsRecord extends DataTransferObject
{
    const TYPE_CNAME = 'CNAME';

    const TYPES = [
        self::TYPE_CNAME,
    ];

    protected ?string $name;
    protected ?string $value;
    protected ?int $ttl;
    protected ?string $type;

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
        $this->ttl = $data['TTL'] ?? null;
        $this->type = $data['Type'] ?? null;
    }

    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    public function setValue(string $value)
    {
        $this->value = $value;

        return $this;
    }

    public function setTtl(int $ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    public function setType(string $type)
    {
        $this->validateType($type);

        $this->type = $type;

        return $this;
    }

    protected function validateType(string $type)
    {
        if (!in_array($type, self::TYPES)) {
            throw new InvalidArgumentException('type');
        }
    }
}
