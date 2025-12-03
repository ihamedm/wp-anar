<?php

namespace Anar\Import;

use Anar\Wizard\ProductManager;
use wpdb;

class ProductsRepository
{
    private wpdb $wpdb;
    private string $table_name;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . ANAR_DB_PRODUCTS_NAME;
    }

    /**
     * Reset the staging table before a new fetch/import cycle.
     */
    public function reset(): void
    {
        $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Persist products fetched from API into staging table.
     *
     * @param array $products
     * @return array{total:int,queued:int,skipped:int}
     */
    public function stage_products(array $products): array
    {
        $stats = [
            'total' => count($products),
            'queued' => 0,
            'skipped' => 0,
        ];

        foreach ($products as $product) {
            // Serialize API product object to WooCommerce-compatible format
            $prepared_product = ProductManager::product_serializer($product);
            
            if (!$prepared_product || empty($prepared_product['sku'])) {
                $stats['skipped']++;
                continue;
            }

            $sku = $prepared_product['sku'];
            $serialized = maybe_serialize($prepared_product);
            // TODO: perf review - consider batching inserts to reduce queries on large datasets.
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "INSERT INTO {$this->table_name} (anar_sku, product_data, status, wc_product_id)
                     VALUES (%s, %s, %s, NULL)
                     ON DUPLICATE KEY UPDATE product_data = VALUES(product_data),
                         status = VALUES(status),
                         wc_product_id = NULL,
                         updated_at = CURRENT_TIMESTAMP",
                    $sku,
                    $serialized,
                    'pending'
                )
            );

            if ($result !== false) {
                $stats['queued']++;
            }
        }

        return $stats;
    }

    public function count_pending(): int
    {
        return (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'pending'
            )
        );
    }

    public function get_pending_batch(int $limit = 5): array
    {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
                'pending',
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    public function delete_products(array $skus): void
    {
        if (empty($skus)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($skus), '%s'));
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE anar_sku IN ({$placeholders})",
                ...$skus
            )
        );
    }

    public function mark_failed(string $sku): void
    {
        $this->wpdb->update(
            $this->table_name,
            [
                'status' => 'failed',
                'updated_at' => current_time('mysql'),
            ],
            ['anar_sku' => $sku]
        );
    }

}

