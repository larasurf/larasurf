<?php

namespace LaraSurf\LaraSurf;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigException;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigValueException;
use League\Flysystem\FileNotFoundException;

class Config
{
    protected array $config;

    public function __construct(protected $filename = 'larasurf.json')
    {
        $path = base_path($filename);

        if (!File::exists($path)) {
            throw new FileNotFoundException($path);
        }

        $contents = File::get($path);

        $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $this->validateConfig($config);

        $this->config = $config;
    }

    public function get(string $key)
    {
        return Arr::get($this->config, $key);
    }

    public function set(string $key, string $value)
    {
        if (!in_array($key, [
            'project-name',
            'project-id',
            'aws-profile',
            'environments.stage.aws-region',
            'environments.production.aws-region',
        ])) {
            throw new InvalidConfigKeyException($key);
        }

        $validator = Validator::make([
            $key => $value,
        ], [
            $key => $this->validationRules()[$key],
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigValueException($key, $validator->getMessageBag()->toArray());
        }

        Arr::set($this->config, $key, $value);
    }

    public function exists(string $key)
    {
        if (!in_array($key, [
            'project-name',
            'project-id',
            'aws-profile',
            'environments.stage',
            'environments.stage.aws-region',
            'environments.production',
            'environments.production.aws-region',
        ])) {
            throw new InvalidConfigKeyException($key);
        }

        return Arr::exists($this->config, $key);
    }

    public function write(): bool
    {
        $json = json_encode($this->config, JSON_PRETTY_PRINT);

        return File::put(base_path($this->filename), $json . PHP_EOL);
    }

    protected function validationRules(): array
    {
        return [
            'project-name' => 'required|regex:/^[a-z0-9-]+$/',
            'project-id' => 'required|regex:/^[a-zA-Z0-9]{16}$/',
            'aws-profile' => 'required|regex:/^[a-zA-Z0-9-_]+$/',
            'environments' => 'array|nullable',
            'environments.stage' => 'present|nullable',
            'environments.stage.aws-region' => [Rule::in(Cloud::AWS_REGIONS)],
            'environments.production' => 'present|nullable',
            'environments.production.aws-region' => [Rule::in(Cloud::AWS_REGIONS)],
        ];
    }

    protected function validateConfig(array $config)
    {
        $validator = Validator::make($config, $this->validationRules());

        if ($validator->fails()) {
            throw new InvalidConfigException($config, $validator->getMessageBag()->toArray());
        }
    }
}
