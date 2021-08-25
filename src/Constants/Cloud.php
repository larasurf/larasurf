<?php

namespace LaraSurf\LaraSurf\Constants;

class Cloud
{
    const AWS_REGION_US_EAST_1 = 'us-east-1';

    const AWS_REGIONS = [
        self::AWS_REGION_US_EAST_1,
    ];

    const ENVIRONMENT_STAGE = 'stage';
    const ENVIRONMENT_PRODUCTION = 'production';

    const ENVIRONMENTS = [
        self::ENVIRONMENT_STAGE,
        self::ENVIRONMENT_PRODUCTION,
    ];
}
