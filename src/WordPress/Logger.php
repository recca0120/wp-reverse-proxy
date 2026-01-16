<?php

namespace Recca0120\ReverseProxy\WordPress;

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger
{
    public function log($level, $message, array $context = []): void
    {
        do_action('reverse_proxy_log', $level, $message, $context);
    }
}
