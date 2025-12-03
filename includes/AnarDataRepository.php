<?php

namespace Anar;

use wpdb;

class AnarDataRepository
{
    private static ?self $instance = null;
    private string $table_name;
    private wpdb $wpdb;

    private function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . ANAR_DB_NAME;
    }

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Normalize the key to ensure _v2 suffix is applied once.
     */
    private function normalize_key(string $key): string
    {
        return str_ends_with($key, '_v2') ? $key : "{$key}_v2";
    }

    /**
     * Store arbitrary data in the wp_anar table.
     */
    public function save(string $key, mixed $data, ?int $page = null, int $processed = 0): bool
    {
        $normalized_key = $this->normalize_key($key);
        $serialized_data = maybe_serialize($data);

        if ($page === null) {
            $this->delete($key);
        }

        $payload = [
            'response'  => $serialized_data,
            'key'       => $normalized_key,
            'processed' => $processed,
        ];

        $formats = ['%s', '%s', '%d'];

        if ($page !== null) {
            $payload['page'] = $page;
            $formats[] = '%d';
        }

        $result = $this->wpdb->replace($this->table_name, $payload, $formats);

        if ($result === false) {
            awca_log("AnarDataRepository::save failed for key {$normalized_key}. Error: " . $this->wpdb->last_error, 'import', 'error');
        }

        return $result !== false;
    }

    /**
     * Retrieve the latest record for a key.
     */
    public function get(string $key, ?int $page = null): array|false
    {
        $normalized_key = $this->normalize_key($key);

        if ($page !== null) {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE `key` = %s AND page = %d ORDER BY id DESC LIMIT 1",
                $normalized_key,
                $page
            );
        } else {
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE `key` = %s ORDER BY id DESC LIMIT 1",
                $normalized_key
            );
        }

        $row = $this->wpdb->get_row($query, ARRAY_A);

        if (!$row) {
            return false;
        }

        $row['response'] = maybe_unserialize($row['response']);

        return $row;
    }

    /**
     * Retrieve all rows for a key (useful for paginated data).
     */
    public function get_all(string $key): array
    {
        $normalized_key = $this->normalize_key($key);
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE `key` = %s ORDER BY created_at ASC",
            $normalized_key
        );

        $rows = $this->wpdb->get_results($query, ARRAY_A) ?: [];

        foreach ($rows as &$row) {
            $row['response'] = maybe_unserialize($row['response']);
        }

        return $rows;
    }

    public function delete(string $key): bool
    {
        $normalized_key = $this->normalize_key($key);
        $deleted = $this->wpdb->delete($this->table_name, ['key' => $normalized_key], ['%s']);

        return $deleted !== false;
    }

    public function delete_page(string $key, int $page): bool
    {
        $normalized_key = $this->normalize_key($key);
        $deleted = $this->wpdb->delete(
            $this->table_name,
            [
                'key'  => $normalized_key,
                'page' => $page,
            ],
            ['%s', '%d']
        );

        return $deleted !== false;
    }

    public function exists(string $key): bool
    {
        $normalized_key = $this->normalize_key($key);

        $query = $this->wpdb->prepare(
            "SELECT COUNT(id) FROM {$this->table_name} WHERE `key` = %s",
            $normalized_key
        );

        $count = (int) $this->wpdb->get_var($query);

        return $count > 0;
    }
}

