<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\Result;
use Illuminate\Support\Str;
use LaraSurf\LaraSurf\Tests\TestCase;

class IamClientTest extends TestCase
{
    public function testUserExists()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('getUser')
            ->andReturn();

        $this->assertTrue($this->iamClient()->userExists($this->faker->word));
    }

    public function testUserDoesntExist()
    {
        $name = $this->faker->word;

        $this->mockAwsIamClient()
            ->shouldReceive('getUser')
            ->andThrow(new AwsException('test', new Command('test')));

        $this->assertFalse($this->iamClient()->userExists($name));
    }

    public function testCreateUser()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('createUser')
            ->andReturn();

        $this->iamClient()->createUser($this->faker->word);
    }

    public function testDeleteUser()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('deleteUser')
            ->andReturn();

        $this->iamClient()->deleteUser($this->faker->word);
    }

    public function testAttachUserPolicy()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('attachUserPolicy')
            ->andReturn();

        $this->iamClient()->attachUserPolicy($this->faker->word, Str::random());
    }


    public function testDetachUserPolicy()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('detachUserPolicy')
            ->andReturn();

        $this->iamClient()->detachUserPolicy($this->faker->word, Str::random());
    }

    public function testCreateAccessKey()
    {
        $id = Str::random();
        $secret = Str::random();

        $this->mockAwsIamClient()
            ->shouldReceive('createAccessKey')
            ->andReturn([
                'AccessKey' => [
                    'AccessKeyId' => $id,
                    'SecretAccessKey' => $secret,
                ],
            ]);

        $keys = $this->iamClient()->createAccessKey($this->faker->word);

        $this->assertEquals($id, $keys->getId());
        $this->assertEquals($secret, $keys->getSecret());
    }

    public function testDeleteAccessKey()
    {
        $this->mockAwsIamClient()
            ->shouldReceive('deleteAccessKey')
            ->andReturn();

        $this->iamClient()->deleteAccessKey($this->faker->word, Str::random());
    }

    public function testListAccessKeys()
    {
        $key1 = Str::random();
        $key2 = Str::random();

        $this->mockAwsIamClient()
            ->shouldReceive('listAccessKeys')
            ->andReturn([
                'AccessKeyMetadata' => [
                    [
                        'AccessKeyId' => $key1,
                    ],
                    [
                        'AccessKeyId' => $key2,
                    ],
                ],
            ]);

        $keys = $this->iamClient()->listAccessKeys($this->faker->word);

        $this->assertTrue(in_array($key1, $keys));
        $this->assertTrue(in_array($key2, $keys));
    }
}
