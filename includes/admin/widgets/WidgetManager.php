<?php

namespace Anar\Admin\Widgets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Widget Manager - Handles all report widgets
 */
class WidgetManager
{
    private static $instance;
    private $widgets = [];

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->register_widgets();
    }

    /**
     * Register all available widgets
     */
    private function register_widgets()
    {
        $this->widgets = [
            'system_info' => new SystemInfoWidget(),
            'database_health' => new DatabaseHealthWidget(),
            'product_stats' => new ProductStatsWidget(),
            'error_logs' => new ErrorLogWidget(),
            'benchmark' => new BenchmarkWidget(),
            'api_health' => new ApiHealthWidget(),
            'cron_health' => new CronHealthWidget(),
            'crontrol' => new CrontrolWidget()
        ];
    }

    /**
     * Get all registered widgets
     */
    public function get_widgets()
    {
        return $this->widgets;
    }

    /**
     * Get a specific widget by ID
     */
    public function get_widget($widget_id)
    {
        return isset($this->widgets[$widget_id]) ? $this->widgets[$widget_id] : null;
    }

    /**
     * Render all widgets
     */
    public function render_all_widgets()
    {
        echo '<div class="anar-widgets-container">';
        
        foreach ($this->widgets as $widget) {
            $widget->render();
        }
        
        echo '</div>';
    }

    /**
     * Render widgets in a grid layout
     */
    public function render_widgets_grid()
    {
        echo '<div class="anar-widgets-grid">';
        
        foreach ($this->widgets as $widget) {
            echo '<div class="anar-widget-grid-item">';
            $widget->render();
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Get widget configurations for JavaScript
     */
    public function get_widget_configs()
    {
        $configs = [];
        
        foreach ($this->widgets as $key => $widget) {
            $configs[$key] = $widget->get_config();
        }
        
        return $configs;
    }

    /**
     * Add a new widget
     */
    public function add_widget($widget_id, $widget_instance)
    {
        if ($widget_instance instanceof AbstractReportWidget) {
            $this->widgets[$widget_id] = $widget_instance;
        }
    }

    /**
     * Remove a widget
     */
    public function remove_widget($widget_id)
    {
        if (isset($this->widgets[$widget_id])) {
            unset($this->widgets[$widget_id]);
        }
    }
}
