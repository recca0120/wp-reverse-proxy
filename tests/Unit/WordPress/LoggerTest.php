<?php

namespace ReverseProxy\Tests\Unit\WordPress;

use ReverseProxy\WordPress\Logger;
use WP_UnitTestCase;

class LoggerTest extends WP_UnitTestCase
{
    public function test_it_implements_psr3_logger_interface()
    {
        $logger = new Logger();

        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function test_it_triggers_wordpress_action_on_log()
    {
        $logger = new Logger();
        $captured = [];

        add_action('reverse_proxy_log', function ($level, $message, $context) use (&$captured) {
            $captured = compact('level', 'message', 'context');
        }, 10, 3);

        $logger->info('Test message', ['key' => 'value']);

        $this->assertEquals('info', $captured['level']);
        $this->assertEquals('Test message', $captured['message']);
        $this->assertEquals(['key' => 'value'], $captured['context']);
    }

    public function test_it_supports_all_log_levels()
    {
        $logger = new Logger();
        $levels = [];

        add_action('reverse_proxy_log', function ($level) use (&$levels) {
            $levels[] = $level;
        }, 10, 3);

        $logger->emergency('test');
        $logger->alert('test');
        $logger->critical('test');
        $logger->error('test');
        $logger->warning('test');
        $logger->notice('test');
        $logger->info('test');
        $logger->debug('test');

        $this->assertEquals(
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            $levels
        );
    }
}
