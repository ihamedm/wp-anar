<?php
namespace Anar\Core;

use WP_Error;

class Image_Downloader{

    private $size_limit;
    private $timeout;
    private $retry_limit;

    public function __construct($size_limit = 5 * 1024 * 1024, $timeout = 60, $retry_limit = 2){
        $this->size_limit = $size_limit;
        $this->timeout = $timeout;
        $this->retry_limit = $retry_limit;
    }

    /**
     * Download an image from a URL and insert it into the WordPress media library.
     *
     * @param string $image_url The URL of the image to download.
     * @param int $product_id The ID of the product to attach the image to.
     * @return int|WP_Error The attachment ID on success, or a WP_Error on failure.
     */
    public function save_image_as_attachment($image_url, int $product_id) {
        $existing_attachment_id = $this->is_image_downloaded($image_url, $product_id);
        if ($existing_attachment_id) {
            awca_log("Image #{$existing_attachment_id} downloaded before, so skipp download again and use it.");
            return $existing_attachment_id;
        }

        // Transform the image URL if needed
        $image_url = $this->transform_image_url($image_url);

        // Get the image file name from the URL
        $image_name = basename($image_url);

        // Get the WordPress upload directory
        $upload_dir = wp_upload_dir();

        $response = $this->fetch_image($image_url);
        if (is_wp_error($response)) {
            return $response;
        }

        // Get the HTTP response code and image data
        $http_code = wp_remote_retrieve_response_code($response);
        $image_data = wp_remote_retrieve_body($response);

        // Check for invalid response or large file size
        $content_length = wp_remote_retrieve_header($response, 'content-length');
        if ($http_code !== 200 || empty($image_data) || $content_length > $this->size_limit) {
            awca_log("Failed to download image from URL: $image_url");
            awca_log("HTTP Code: $http_code");
            if ($content_length > $this->size_limit) {
                awca_log("Image file larger than ".($this->size_limit/(1024*1024))."MB: $content_length bytes");
            }
            return new WP_Error('image_download_failed', __('Failed to download image.'));
        }

        // Ensure the file name is unique in the upload directory
        $unique_file_name = wp_unique_filename($upload_dir['path'], $image_name);
        $file_path = $upload_dir['path'] . '/' . $unique_file_name;

        // Save the image data to the file
        $file_saved = @file_put_contents($file_path, $image_data);
        if ($file_saved === false) {
            awca_log("Failed to save image to file: $file_path");
            return new WP_Error('image_save_failed', __('Failed to save image.'));
        }

        // Check the file type for the attachment metadata
        $wp_filetype = wp_check_filetype($file_path, null);

        // Prepare the attachment data array
        $attachment_data = array(
            'guid'           => $upload_dir['url'] . '/' . $unique_file_name,
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => sanitize_file_name($image_name),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        // Insert the attachment into the WordPress media library
        $attachment_id = wp_insert_attachment($attachment_data, $file_path, $product_id);
        if (is_wp_error($attachment_id)) {
            awca_log("Failed to insert attachment into media library: " . $attachment_id->get_error_message());
            return $attachment_id;
        }

        // Ensure the necessary file includes for generating attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Generate and update the attachment metadata
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        $metadata_result = wp_update_attachment_metadata($attachment_id, $attachment_metadata);
        if (!$metadata_result) {
            awca_log("Failed to update attachment metadata for attachment ID: $attachment_id");
        }

        // Mark this image URL as downloaded
        $this->mark_image_as_downloaded($image_url, $product_id, $attachment_id);

        awca_log("image #{$attachment_id} uploaded - Product #{$product_id}");
        return $attachment_id;
    }

    /**
     * Download an image from a URL, insert it into the media library, and set it as the product's featured image.
     *
     * @param int $product_id The ID of the product to set the image for.
     * @param string $image_url The URL of the image to download.
     * @return int|WP_Error The attachment ID on success, or a WP_Error on failure.
     */
    public function set_product_thumbnail($product_id, $image_url) {
        // Insert the attachment into the WordPress media library
        $attachment_id = $this->save_image_as_attachment($image_url, $product_id);

        // Check if there was an error in downloading and inserting the image
        if (is_wp_error($attachment_id)) {
            awca_log("Failed to download and insert attachment for product ID: $product_id. Error: " . $attachment_id->get_error_message());
            // Clean this meta to skip from cron job check
            delete_post_meta($product_id, '_product_image_url', false);
            return $attachment_id;
        }

        // Set the downloaded image as the product's featured image
        $thumbnail_result = set_post_thumbnail($product_id, $attachment_id);

        if (!$thumbnail_result) {
            awca_log("Failed to set product thumbnail for product ID: $product_id");
            // Clean this meta to skip from cron job check
            delete_post_meta($product_id, '_product_image_url', false);
            return new WP_Error('thumbnail_set_failed', __('Failed to set product thumbnail.'));
        }

        // Clean this meta to skip from cron job check
        delete_post_meta($product_id, '_product_image_url', false);
        awca_log('Product #'.$product_id.' thumbnail is set');
        return $attachment_id;
    }

    /**
     * Download images from URLs, insert them into the media library, and set them as the product's gallery images.
     *
     * @param int $product_id The ID of the product to set the gallery images for.
     * @param array $image_urls An array of image URLs to download and set as the product gallery.
     * @return array|WP_Error An array of attachment IDs on success, or a WP_Error on failure.
     */
    public function set_product_gallery_deprecated($product_id, $image_urls, $gallery_image_limit = 5) {
        $attachment_ids = array();
        $counter = 0;

        foreach ($image_urls as $image_url) {
            if ($counter >= $gallery_image_limit) {
                break;
            }

            // Download and upload the image
            $attachment_id = $this->save_image_as_attachment($image_url, $product_id);

            if (is_wp_error($attachment_id)) {
                awca_log("Failed to download and insert attachment for product ID: $product_id. Error: " . $attachment_id->get_error_message(), 'error');
                return $attachment_id; // Return WP_Error if download/upload fails
            }

            $attachment_ids[] = $attachment_id;
            $counter++;
        }

        // Set product gallery images if there are attachment IDs
        if (!empty($attachment_ids)) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_gallery_image_ids($attachment_ids);
                $product->save();
            } else {
                awca_log("Failed to get product with ID: $product_id", 'error');
                return new WP_Error('product_not_found', __('Product not found.'));
            }
        }
        awca_log('Product #'.$product_id.' gallery is set', 'debug');
        return $attachment_ids;
    }


    /**
     * Set product gallery images from array of image URLs
     *
     * @param int   $product_id         Product ID
     * @param array $image_urls         Array of image URLs
     * @param int   $gallery_image_limit Maximum number of images to use
     * @return array|WP_Error Array of attachment IDs or WP_Error
     */
    public function set_product_gallery($product_id, $image_urls, $gallery_image_limit = 5) {
        if (!is_array($image_urls) || empty($image_urls)) {
            awca_log("No valid image URLs provided for product ID: $product_id", 'warning');
            return new \WP_Error('invalid_image_urls', __('No valid image URLs provided.'));
        }

        if (!$product_id || !is_numeric($product_id)) {
            awca_log("Invalid product ID provided: $product_id", 'error');
            return new \WP_Error('invalid_product_id', __('Invalid product ID provided.'));
        }

        // Check if product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            awca_log("Product not found with ID: $product_id", 'error');
            return new \WP_Error('product_not_found', __('Product not found.'));
        }

        $attachment_ids = [];
        $successful_downloads = 0;
        $failed_downloads = 0;

        // Limit the number of images to process
        $image_urls = array_slice($image_urls, 0, $gallery_image_limit);

        foreach ($image_urls as $image_url) {
            // Validate URL
            if (empty($image_url) || !filter_var($image_url, FILTER_VALIDATE_URL)) {
                awca_log("Invalid image URL for product ID: $product_id. URL: $image_url", 'warning');
                $failed_downloads++;
                continue; // Skip this URL but continue processing others
            }

            try {
                // Download and upload the image
                $attachment_id = $this->save_image_as_attachment($image_url, $product_id);

                if (is_wp_error($attachment_id)) {
                    awca_log(
                        "Failed to download image for product ID: $product_id. URL: $image_url. Error: " .
                        $attachment_id->get_error_message(),
                        'error'
                    );
                    $failed_downloads++;
                    continue; // Skip this URL but continue processing others
                }

                $attachment_ids[] = $attachment_id;
                $successful_downloads++;

            } catch (\Exception $e) {
                awca_log(
                    "Exception while processing image for product ID: $product_id. URL: $image_url. Error: " .
                    $e->getMessage(),
                    'error'
                );
                $failed_downloads++;
                continue; // Skip this URL but continue processing others
            }
        }

        // Set product gallery images if there are attachment IDs
        if (!empty($attachment_ids)) {
            try {
                // Merge with existing gallery images if needed
                // Uncomment the below lines if you want to keep existing gallery images
                // $existing_gallery_ids = $product->get_gallery_image_ids();
                // $attachment_ids = array_merge($existing_gallery_ids, $attachment_ids);

                $product->set_gallery_image_ids($attachment_ids);
                $product->save();

                awca_log(
                    "Product #$product_id gallery updated successfully. " .
                    "Added: $successful_downloads images. Failed: $failed_downloads images.",
                    'info'
                );

                return [
                    'attachment_ids' => $attachment_ids,
                    'successful' => $successful_downloads,
                    'failed' => $failed_downloads
                ];

            } catch (\Exception $e) {
                awca_log(
                    "Failed to update gallery for product ID: $product_id. Error: " . $e->getMessage(),
                    'error'
                );
                return new \WP_Error(
                    'gallery_update_failed',
                    __('Failed to update product gallery.'),
                    [
                        'product_id' => $product_id,
                        'error_message' => $e->getMessage(),
                        'attachment_ids' => $attachment_ids
                    ]
                );
            }
        } else {
            $status = $failed_downloads > 0 ? 'All downloads failed' : 'No images to process';
            awca_log("$status for product ID: $product_id", 'warning');
            return new \WP_Error(
                'no_gallery_images',
                __('No gallery images were successfully downloaded.'),
                [
                    'product_id' => $product_id,
                    'failed_downloads' => $failed_downloads
                ]
            );
        }
    }

    /**
     * Check if the URL starts with the expected prefix.
     * @param string $url
     * @return string Transformed URL.
     */
    public function transform_image_url($url) {
        $prefix = "https://s3.c22.wtf/";
        if (strpos($url, $prefix) === 0) {
            // Replace the initial part of the URL and add the new prefix.
            $new_prefix = "https://s3.anar360.com/_img/width_1024/https://s3.anar360.com/";
            return str_replace($prefix, $new_prefix, $url);
        } else {
            // If the URL does not match the expected pattern, return it unchanged.
            return $url;
        }
    }


    /**
     * Attempt to fetch an image from a URL with retries.
     *
     * @param string $image_url The URL of the image to fetch.
     * @return array|WP_Error Array containing the response, or a WP_Error on failure.
     */
    private function fetch_image($image_url) {
        $attempts = 0;

        while ($attempts < $this->retry_limit) {
            $response = wp_remote_get($image_url, array(
                'timeout'     => $this->timeout,
                'redirection' => 10,
                'sslverify'   => false,  // Add this line to disable SSL verification (use with caution)
            ));

            if (!is_wp_error($response)) {
                return $response;
            }

            $attempts++;
            awca_log("Retrying download for URL: $image_url. Attempt #$attempts");
        }

        awca_log("Failed to download image from URL after $this->retry_limit attempts: $image_url");
        return new WP_Error('image_download_failed', __('Failed to download image after several attempts.'));
    }


    /**
     * Check if an image has already been downloaded.
     * @param string $image_url The URL of the image.
     * @param int $product_id The ID of the product.
     * @return int|false The attachment ID if the image is already downloaded, false otherwise.
     */
    private function is_image_downloaded($image_url, $product_id) {
        $downloaded = get_post_meta($product_id, '_awca_downloaded_images', true);
        if (is_array($downloaded)) {
            foreach ($downloaded as $entry) {
                if ($entry['url'] === $image_url) {
                    return $entry['attachment_id'];
                }
            }
        }
        return false;
    }

    /**
     * Mark an image URL as downloaded.
     * @param string $image_url The URL of the image.
     * @param int $product_id The ID of the product.
     * @param int $attachment_id The ID of the attachment.
     */
    private function mark_image_as_downloaded($image_url, $product_id, $attachment_id) {
        $downloaded = get_post_meta($product_id, '_awca_downloaded_images', true);
        if (!is_array($downloaded)) {
            $downloaded = array();
        }
        $downloaded[] = array(
            'url' => $image_url,
            'attachment_id' => $attachment_id
        );
        update_post_meta($product_id, '_awca_downloaded_images', $downloaded);
    }

}
