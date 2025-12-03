<?php

namespace Anar;

use wpdb;

/**
 * Repository for managing individual Anar data items (categories, attributes, products)
 * stored in the wp_anar_data table with pagination support.
 */
class AnarDataItemRepository
{
    private static ?self $instance = null;
    private string $table_name;
    private wpdb $wpdb;

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'anar_data';
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Save or update an item in the anar_data table.
     * 
     * @param string $key Type identifier ('category', 'attribute', or anar_sku for products)
     * @param string $anar_id The Anar API _id field
     * @param mixed $data The item data (will be serialized)
     * @param string|null $status Status: 'pending', 'processed', 'error' (default: 'pending')
     * @param int|null $wc_id WooCommerce ID if mapped (default: null)
     * @return bool True on success, false on failure
     */
    public function save_item(string $key, string $anar_id, mixed $data, ?string $status = 'pending', ?int $wc_id = null): bool
    {
        $serialized_data = maybe_serialize($data);

        $payload = [
            'key' => $key,
            '_id' => $anar_id,
            'data' => $serialized_data,
            'status' => $status ?? 'pending',
        ];

        $formats = ['%s', '%s', '%s', '%s'];

        if ($wc_id !== null) {
            $payload['wc_id'] = $wc_id;
            $formats[] = '%d';
        }

        // Use REPLACE to handle updates (based on unique key constraint)
        $result = $this->wpdb->replace($this->table_name, $payload, $formats);

        if ($result === false) {
            awca_log("AnarDataItemRepository::save_item failed for key '{$key}', _id '{$anar_id}'. Error: " . $this->wpdb->last_error, 'import', 'error');
        }

        return $result !== false;
    }

    /**
     * Get a single item by key and _id.
     * 
     * @param string $key Type identifier
     * @param string $anar_id The Anar API _id field
     * @return array|false Item data or false if not found
     */
    public function get_item(string $key, string $anar_id): array|false
    {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE `key` = %s AND `_id` = %s LIMIT 1",
            $key,
            $anar_id
        );

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return false;
        }

        $row['data'] = maybe_unserialize($row['data']);
        return $row;
    }

    /**
     * Get all items of a specific type (key).
     * 
     * @param string $key Type identifier ('category', 'attribute', or anar_sku pattern)
     * @param string|null $status Optional status filter
     * @return array Array of items
     */
    public function get_items_by_key(string $key, ?string $status = null): array
    {
        if ($status) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE `key` = %s AND `status` = %s ORDER BY created_at ASC",
                $key,
                $status
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE `key` = %s ORDER BY created_at ASC",
                $key
            );
        }

        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        foreach ($rows as &$row) {
            $row['data'] = maybe_unserialize($row['data']);
        }

        return $rows;
    }

    /**
     * Update the status of an item.
     * 
     * @param string $key Type identifier
     * @param string $anar_id The Anar API _id field
     * @param string $status New status
     * @return bool True on success, false on failure
     */
    public function update_status(string $key, string $anar_id, string $status): bool
    {
        $result = $this->wpdb->update(
            $this->table_name,
            ['status' => $status],
            [
                'key' => $key,
                '_id' => $anar_id,
            ],
            ['%s'],
            ['%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Update the WooCommerce ID of an item.
     * 
     * @param string $key Type identifier
     * @param string $anar_id The Anar API _id field
     * @param int $wc_id WooCommerce ID
     * @return bool True on success, false on failure
     */
    public function update_wc_id(string $key, string $anar_id, int $wc_id): bool
    {
        $result = $this->wpdb->update(
            $this->table_name,
            ['wc_id' => $wc_id],
            [
                'key' => $key,
                '_id' => $anar_id,
            ],
            ['%d'],
            ['%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Delete a single item.
     * 
     * @param string $key Type identifier
     * @param string $anar_id The Anar API _id field
     * @return bool True on success, false on failure
     */
    public function delete_item(string $key, string $anar_id): bool
    {
        $deleted = $this->wpdb->delete(
            $this->table_name,
            [
                'key' => $key,
                '_id' => $anar_id,
            ],
            ['%s', '%s']
        );

        return $deleted !== false;
    }

    /**
     * Delete all items of a specific type (key).
     * 
     * @param string $key Type identifier
     * @return bool True on success, false on failure
     */
    public function delete_all_by_key(string $key): bool
    {
        $deleted = $this->wpdb->delete(
            $this->table_name,
            ['key' => $key],
            ['%s']
        );

        return $deleted !== false;
    }

    /**
     * Count items of a specific type, optionally filtered by status.
     * 
     * @param string $key Type identifier
     * @param string|null $status Optional status filter
     * @return int Count of items
     */
    public function count_by_key(string $key, ?string $status = null): int
    {
        if ($status) {
            $query = $this->wpdb->prepare(
                "SELECT COUNT(id) FROM {$this->table_name} WHERE `key` = %s AND `status` = %s",
                $key,
                $status
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT COUNT(id) FROM {$this->table_name} WHERE `key` = %s",
                $key
            );
        }

        return (int) $this->wpdb->get_var($query);
    }

    /**
     * Check if an item exists.
     * 
     * @param string $key Type identifier
     * @param string $anar_id The Anar API _id field
     * @return bool True if exists, false otherwise
     */
    public function exists(string $key, string $anar_id): bool
    {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(id) FROM {$this->table_name} WHERE `key` = %s AND `_id` = %s",
            $key,
            $anar_id
        );

        $count = (int) $this->wpdb->get_var($query);
        return $count > 0;
    }
}

