<?php

namespace LaraSurf\LaraSurf\Tests\Unit\AwsClients;

use Aws\Command;
use Aws\Exception\AwsException;
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

    public function testCreateAccessKeys()
    {
        $id = Str::random();
        $secret = Str::random();

        $this->mockAwsIamClient()
            ->shouldReceive('createAccessKey')
            ->andReturn([
                'AccessKeyId' => $id,
                'SecretAccessKey' => $secret,
            ]);

        $keys = $this->iamClient()->createAccessKeys($this->faker->word);

        $this->assertEquals($id, $keys->getId());
        $this->assertEquals($secret, $keys->getSecret());
    }
}
