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

    const USER_CIRCLECI = 'circleci';

    const USERS = [
        self::USER_CIRCLECI,
    ];

    const DB_INSTANCE_TYPES = [
        'db.t2.small',
        'db.t2.medium',
        'db.m5.large',
        'db.m5.xlarge',
    ];

    const DB_STORAGE_MIN_GB = 20;

    const DB_STORAGE_MAX_GB = 70368; // 64 tebibytes;

    const CACHE_NODE_TYPES = [
        'cache.t2.micro',
        'cache.t2.small',
        'cache.t2.medium',
        'cache.m5.large',
        'cache.m5.xlarge',
        'cache.m5.2xlarge',
        'cache.m5.4xlarge',
        'cache.m5.12xlarge',
        'cache.m5.24xlarge',
        'cache.m4.large',
        'cache.m4.xlarge',
        'cache.m4.2xlarge',
        'cache.m4.4xlarge',
        'cache.m4.10xlarge',
    ];

    const FARGATE_CPU_VALUES = [
        '256',
        '512',
        '1024',
        '2048',
        '4096',
    ];

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
