<?php

namespace Anar\Admin\Widgets;

use Anar\Core\Logger;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Abstract base class for report widgets
 */
abstract class AbstractReportWidget
{
    protected $logger;
    protected $widget_id;
    protected $title;
    protected $description;
    protected $icon;
    protected $ajax_action;
    protected $button_text;
    protected $button_class;

    public function __construct()
    {
        $this->logger = new Logger();
        $this->init();
    }

    /**
     * Initialize the widget - should be implemented by child classes
     */
    abstract protected function init();

    /**
     * Get widget configuration
     */
    public function get_config()
    {
        return [
            'widget_id' => $this->widget_id,
            'title' => $this->title,
            'description' => $this->description,
            'icon' => $this->icon,
            'ajax_action' => $this->ajax_action,
            'button_text' => $this->button_text,
            'button_class' => $this->button_class
        ];
    }

    /**
     * Render the widget HTML
     */
    public function render()
    {
        $config = $this->get_config();
        ?>
        <div class="anar-report-widget" id="<?php echo esc_attr($config['widget_id']); ?>">
            <div class="anar-widget-header">
                <div class="anar-widget-icon">
                    <?php echo $config['icon']; ?>
                </div>
                <div class="anar-widget-info">
                    <h3 class="anar-widget-title"><?php echo esc_html($config['title']); ?></h3>
                    <p class="anar-widget-description"><?php echo esc_html($config['description']); ?></p>
                </div>
            </div>
            <div class="anar-widget-content">
                <div class="anar-widget-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span>در حال بارگذاری...</span>
                </div>
                <div class="anar-widget-results" style="display: none;">
                    <!-- Results will be loaded here -->
                </div>
                <div class="anar-widget-error" style="display: none;">
                    <!-- Error messages will be shown here -->
                </div>
            </div>
            <div class="anar-widget-actions">
                <button class="button <?php echo esc_attr($config['button_class']); ?>" 
                        data-action="<?php echo esc_attr($config['ajax_action']); ?>"
                        data-widget="<?php echo esc_attr($config['widget_id']); ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php echo esc_html($config['button_text']); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX request for this widget
     */
    public function handle_ajax()
    {
        $this->verify_permissions();

        try {
            $data = $this->get_report_data();
            wp_send_json_success($data);
        } catch (\Exception $e) {
            $this->logger->log('Error in widget ' . $this->widget_id . ': ' . $e->getMessage(), 'widgets', 'error');
            wp_send_json_error(['message' => 'خطا در دریافت گزارش: ' . $e->getMessage()]);
        }
    }

    /**
     * Get report data - must be implemented by child classes
     */
    abstract protected function get_report_data();

    /**
     * Verify user permissions
     */
    protected function verify_permissions()
    {
        // Check if nonce is provided
        if (!isset($_POST['nonce'])) {
            wp_send_json_error(['message' => 'Nonce not provided']);
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'awca_ajax_nonce')) {
            wp_send_json_error(['message' => 'Security check failed - invalid nonce']);
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }
    }

    /**
     * Log a message
     */
    protected function log($message, $level = 'info')
    {
        $this->logger->log($message, 'widgets', $level);
    }
}