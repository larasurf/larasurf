<?php

namespace LaraSurf\LaraSurf\Constants;

class Cloud
{
    /**
     * The supported AWS regions.
     */
    const AWS_REGION_US_EAST_1 = 'us-east-1';

    /**
     * All supported AWS regions.
     */
    const AWS_REGIONS = [
        self::AWS_REGION_US_EAST_1,
    ];

    /**
     * The supported application environments.
     */
    const ENVIRONMENT_STAGE = 'stage';
    const ENVIRONMENT_PRODUCTION = 'production';

    /**
     * All supported application environments.
     */
    const ENVIRONMENTS = [
        self::ENVIRONMENT_STAGE,
        self::ENVIRONMENT_PRODUCTION,
    ];

    /**
     * The supported users.
     */
    const USER_CIRCLECI = 'circleci';

    /**
     * All supported users.
     */
    const USERS = [
        self::USER_CIRCLECI,
    ];

    /**
     * The supported database instance types.
     */
    const DB_INSTANCE_TYPES = [
        'db.t3.micro',
        'db.t3.small',
        'db.t3.medium',
        'db.t3.large',
        'db.t3.xlarge',
        'db.t3.2xlarge',
        'db.m5.large',
        'db.m5.xlarge',
        'db.m5.2xlarge',
        'db.m5.4xlarge',
        'db.m5.8xlarge',
        'db.m5.12xlarge',
        'db.m5.16xlarge',
        'db.m5.24xlarge',
    ];

    /**
     * The minimum database storage size in GB.
     */
    const DB_STORAGE_MIN_GB = 20;

    /**
     * The maximum database storage size in GB.
     */
    const DB_STORAGE_MAX_GB = 70368; // 64 tebibytes;

    /**
     * The supported cache node types.
     */
    const CACHE_NODE_TYPES = [
        'cache.t3.micro',
        'cache.t3.small',
        'cache.t3.medium',
        'cache.m5.large',
        'cache.m5.xlarge',
        'cache.m5.2xlarge',
        'cache.m5.4xlarge',
        'cache.m5.12xlarge',
        'cache.m5.24xlarge',
    ];

    /**
     * The supported Fargate CPU values.
     */
    const FARGATE_CPU_VALUES = [
        '256',
        '512',
        '1024',
        '2048',
        '4096',
    ];

    /**
     * A mapping of Fargate CPU value => supported memory values.
     */
    const FARGATE_CPU_MEMORY_VALUES_MAP = [
        '256' => [
            '512',
            '1024',
            '2048',
        ],
        '512' => [
            '1024',
            '2048',
            '3072',
            '4096',
        ],
        '1024' => [
            '2048',
            '3072',
            '4096',
            '5120',
            '6144',
            '7168',
            '8192',
        ],
        '2048' => [
            '4096',
            '5120',
            '6144',
            '7168',
            '8192',
            '9216',
            '10240',
            '11264',
            '12288',
            '13312',
            '14336',
            '15360',
            '16384',
        ],
        '4096' => [
            '8192',
            '9216',
            '10240',
            '11264',
            '12288',
            '13312',
            '14336',
            '15360',
            '16384',
            '17408',
            '18432',
            '19456',
            '20480',
            '21504',
            '22528',
            '23552',
            '24576',
            '25600',
            '26624',
            '27648',
            '28672',
            '29696',
            '30720',
        ],
    ];
}
