<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

$phpVersion = getenv('RECTOR_TARGET_PHP') ?: '8.2';

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/includes',
        __DIR__.'/tests',
        __DIR__.'/reverse-proxy.php',
    ])
    ->withDowngradeSets(
        php82: $phpVersion === '8.2',
        php81: $phpVersion === '8.1',
        php80: $phpVersion === '8.0',
        php74: $phpVersion === '7.4',
        php73: $phpVersion === '7.3',
        php72: $phpVersion === '7.2',
    );
