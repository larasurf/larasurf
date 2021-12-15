<?php

namespace LaraSurf\LaraSurf\Tests\Feature\Commands;

use LaraSurf\LaraSurf\Config;
use LaraSurf\LaraSurf\Tests\TestCase;

class ConfigureNewEnvironmentTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValidateNewEnvironmentsLocalProductionToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local-production');

        $this->artisan("larasurf:configure-new-environments validate-new-environments --environments stage")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValidateNewEnvironmentsLocalToLocalProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan("larasurf:configure-new-environments validate-new-environments --environments production")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testValidateNewEnvironmentsLocalToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan("larasurf:configure-new-environments validate-new-environments --environments stage-production")
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetNewBranchesLocalToLocalProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan("larasurf:configure-new-environments get-new-branches --environments production")
            ->expectsOutput('develop')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetNewBranchesLocalProductionToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local-production');

        $this->artisan("larasurf:configure-new-environments get-new-branches --environments stage")
            ->expectsOutput('stage')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGetNewBranchesLocalToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan("larasurf:configure-new-environments get-new-branches --environments stage-production")
            ->expectsOutput('stage-develop')
            ->assertExitCode(0);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModifyLaraSurfConfigLocalToLocalProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan('larasurf:configure-new-environments modify-larasurf-config --environments production')
            ->expectsOutput("File 'larasurf.json' updated successfully")
            ->assertExitCode(0);

        $config = new Config();
        $this->assertTrue($config->exists('environments.production'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModifyLaraSurfConfigLocalToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local');

        $this->artisan('larasurf:configure-new-environments modify-larasurf-config --environments stage-production')
            ->expectsOutput("File 'larasurf.json' updated successfully")
            ->assertExitCode(0);

        $config = new Config();
        $this->assertTrue($config->exists('environments.stage'));
        $this->assertTrue($config->exists('environments.production'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testModifyLaraSurfConfigLocalProductionToLocalStageProduction()
    {
        $this->createGitHead('main');

        $this->createValidLaraSurfConfig('local-production');

        $this->artisan('larasurf:configure-new-environments modify-larasurf-config --environments stage')
            ->expectsOutput("File 'larasurf.json' updated successfully")
            ->assertExitCode(0);

        $config = new Config();
        $this->assertTrue($config->exists('environments.stage'));
        $this->assertTrue($config->exists('environments.production'));
    }
}
