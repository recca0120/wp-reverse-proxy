<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/includes',
        __DIR__.'/tests',
        __DIR__.'/reverse-proxy.php',
    ])
    ->withDowngradeSets(
        php82: getenv('RECTOR_PHP82') === 'true',
        php81: getenv('RECTOR_PHP81') === 'true',
        php80: getenv('RECTOR_PHP80') === 'true',
        php74: getenv('RECTOR_PHP74') === 'true',
        php73: getenv('RECTOR_PHP73') === 'true',
        php72: getenv('RECTOR_PHP72') === 'true',
    );
