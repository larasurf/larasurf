<?php

namespace LaraSurf\LaraSurf;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LaraSurf\LaraSurf\Constants\Cloud;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigException;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigKeyException;
use LaraSurf\LaraSurf\Exceptions\Config\InvalidConfigValueException;

class Config
{
    /**
     * The JSON decoded LaraSurf configuration file.
     *
     * @var array|null
     */
    protected ?array $config = null;

    /**
     * The name of the JSON encoded LaraSurf configuration file.
     *
     * @var string|null
     */
    protected ?string $filename = null;

    /**
     * Determines if the LaraSurf configuration file has been loaded.
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->filename && $this->config;
    }

    /**
     * JSON decodes the specified file name.
     *
     * @param string $filename
     * @return $this
     * @throws FileNotFoundException
     * @throws InvalidConfigException
     * @throws \JsonException
     */
    public function load(string $filename = 'larasurf.json'): static
    {
        $path = base_path($filename);

        if (!File::exists($path)) {
            throw new FileNotFoundException($path);
        }

        $contents = File::get($path);

        $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        $this->validateConfig($config);

        $this->filename = $filename;
        $this->config = $config;

        return $this;
    }

    /**
     * Get a configuration value using dot notation.
     *
     * @param string $key
     * @return array|\ArrayAccess|mixed
     */
    public function get(string $key)
    {
        return Arr::get($this->config, $key);
    }

    /**
     * Set a configuration value using dot notation.
     *
     * @param string $key
     * @param string|null $value
     * @throws InvalidConfigKeyException
     * @throws InvalidConfigValueException
     */
    public function set(string $key, ?string $value)
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

        $validator = Validator::make([
            'data' => $value,
        ], [
            'data' => $this->validationRules()[$key],
        ]);

        if ($validator->fails()) {
            throw new InvalidConfigValueException($key, $validator->getMessageBag()->toArray());
        }

        Arr::set($this->config, $key, $value);
    }

    /**
     * Determine if a configuration value exists using dot notation.
     *
     * @param string $key
     * @return bool
     * @throws InvalidConfigKeyException
     */
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

        return Arr::get($this->config, $key, false) !== false;
    }

    /**
     * Write the current configuration to a file.
     *
     * @return bool
     */
    public function write(): bool
    {
        $json = json_encode($this->config, JSON_PRETTY_PRINT);

        return File::put(base_path($this->filename), $json . PHP_EOL);
    }

    /**
     * Get the validation rules for a LaraSurf configuration file.
     *
     * @return array
     */
    protected function validationRules(): array
    {
        return [
            'project-name' => 'required|regex:/^[a-z0-9-]+$/',
            'project-id' => 'required|regex:/^[0-9]{6}$/',
            'aws-profile' => 'required|regex:/^[a-zA-Z0-9-_]+$/',
            'environments' => 'array|nullable',
            'environments.stage' => 'nullable',
            'environments.stage.aws-region' => [Rule::in(Cloud::AWS_REGIONS)],
            'environments.production' => 'nullable',
            'environments.production.aws-region' => [Rule::in(Cloud::AWS_REGIONS)],
        ];
    }

    /**
     * Validates a LaraSurf configuration file using validation rules.
     *
     * @param array $config
     * @throws InvalidConfigException
     */
    protected function validateConfig(array $config)
    {
        $validator = Validator::make($config, $this->validationRules());

        if ($validator->fails()) {
            throw new InvalidConfigException($config, $validator->getMessageBag()->toArray());
        }
    }
}
