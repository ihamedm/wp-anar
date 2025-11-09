<?php

namespace Anar\Admin;

use Anar\Core\Logger;
use Anar\Admin\ReportHandlers;
use Anar\Admin\Widgets\WidgetManager;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Tools
 * Handles various admin tools and utilities
 */
class Tools
{
    private static $instance;
    private $logger;
    private $report_handlers;

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->logger = new Logger();
        $this->report_handlers = new ReportHandlers();
        
        // Initialize widget manager to register AJAX handlers
        $this->init_widgets();
        
        // Enqueue status tools scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_status_tools_assets']);
        
        // Register AJAX handlers using the generic system
        add_action('wp_ajax_anar_get_zero_profit_products', [$this->report_handlers, 'get_zero_profit_products']);
        add_action('wp_ajax_anar_get_deprecated_products', [$this->report_handlers, 'get_deprecated_products']);
        add_action('wp_ajax_anar_change_deprecated_status', [$this->report_handlers, 'change_deprecated_status']);
        add_action('wp_ajax_anar_get_duplicate_products', [$this->report_handlers, 'get_duplicate_products']);
        add_action('wp_ajax_anar_change_duplicate_status', [$this->report_handlers, 'change_duplicate_status']);
    }

    /**
     * Initialize widgets to register their AJAX handlers
     */
    private function init_widgets()
    {
        // Initialize widget manager to register AJAX handlers
        WidgetManager::get_instance();
    }

    /**
     * Enqueue status tools assets on the status page
     */
    public function enqueue_status_tools_assets($hook)
    {
        // Check if we're on the tools page using $_GET parameter (more reliable than hook name)
        if (!isset($_GET['page']) || $_GET['page'] !== 'tools') {
            return;
        }

        // Check if we're on the status tab
        $active_tab = $_GET['tab'] ?? 'tools';
        if ($active_tab !== 'status') {
            return;
        }

        // Enqueue status-tools script
        wp_enqueue_script(
            'anar-status-tools',
            ANAR_PLUGIN_URL . '/assets/dist/status-tools.min.js',
            ['jquery'],
            ANAR_PLUGIN_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script('anar-status-tools', 'awca_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('awca_ajax_nonce'),
        ));
    }

    /**
     * Log a message
     */
    private function log($message, $level = 'info')
    {
        $this->logger->log($message, 'tools', $level);
    }
}
