<?php
namespace Anar\Tests\Unit;

use Anar\CronJob_Process_Products;
use Anar\Core\Logger;
use PHPUnit\Framework\TestCase;

class CronJobProcessProductsTest extends TestCase
{
    private $cronJobProcessor;
    private $logger;
    private static $logDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$logDir = __DIR__ . '/../tmp/logs';
        if (!file_exists(self::$logDir)) {
            mkdir(self::$logDir, 0777, true);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset transients before each test
        global $wp_transients;
        $wp_transients = [];

        // Create a real logger instance with a test directory
        $this->logger = $this->createMock(Logger::class);

        // Create the CronJob_Process_Products instance
        $this->cronJobProcessor = CronJob_Process_Products::get_instance();
        $this->cronJobProcessor->logger = $this->logger;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up transients after each test
        global $wp_transients;
        $wp_transients = [];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Clean up the test directory
        if (file_exists(self::$logDir)) {
            array_map('unlink', glob(self::$logDir . '/*.*'));
            rmdir(self::$logDir);
        }
    }

    public function testDetectsStuckProcessByStartTime()
    {
        // Simulate a process that started more than 5 minutes ago
        $sixMinutesAgo = time() - 360;
        \set_transient('awca_create_product_row_start_time', $sixMinutesAgo);
        \set_transient('awca_create_product_row_on_progress', 'yes');

        // Set up logger expectations
        $this->logger->expects($this->atLeastOnce())
            ->method('log')
            ->with($this->stringContains('Process has been running for more than 5 minutes, likely stuck'));

        // Run the check
        $this->cronJobProcessor->check_for_stuck_processes();

        // Verify that process was detected as stuck
        $this->assertFalse(\get_transient('awca_create_product_row_on_progress'));
    }

    public function testDetectsStuckProcessByMissingHeartbeat()
    {
        // Simulate a missing heartbeat for more than 8 minutes
        $tenMinutesAgo = time() - 600;
        \set_transient('awca_create_product_heartbeat', $tenMinutesAgo);
        \set_transient('awca_create_product_row_on_progress', 'yes');
        \set_transient('awca_create_product_row_start_time', time() - 60);

        // Set up logger expectations
        $this->logger->expects($this->atLeastOnce())
            ->method('log')
            ->with($this->stringContains('No heartbeat detected for more than 8 minutes, likely stuck'));

        // Run the check
        $this->cronJobProcessor->check_for_stuck_processes();

        // Verify that process was detected as stuck
        $this->assertFalse(\get_transient('awca_create_product_row_on_progress'));
    }

    public function testNoStuckProcessWhenEverythingNormal()
    {
        // Simulate a normal running process
        $twoMinutesAgo = time() - 120;
        \set_transient('awca_create_product_row_start_time', $twoMinutesAgo);
        \set_transient('awca_create_product_heartbeat', time() - 60);
        \set_transient('awca_create_product_row_on_progress', 'yes');

        // Set up logger expectations - should not log any stuck process messages
        $this->logger->expects($this->never())
            ->method('log')
            ->with($this->stringContains('likely stuck'));

        // Run the check
        $this->cronJobProcessor->check_for_stuck_processes();

        // Verify that process was not detected as stuck
        $this->assertEquals('yes', \get_transient('awca_create_product_row_on_progress'));
    }
}