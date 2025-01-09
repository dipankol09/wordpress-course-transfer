<?php

/**
 * Plugin Name: SKD Course Transfer Plugin
 * Description: Transfer courses and lessons between WordPress sites.
 * Version: 1.0
 * Author: SKD
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('COURSE_MIGRATION_PATH', plugin_dir_path(__FILE__));
define('COURSE_MIGRATION_URL', plugin_dir_url(__FILE__));

// Add admin menu items
add_action('admin_menu', function () {
    add_menu_page(
        'Course Migration',
        'Course Migration',
        'manage_options',
        'course-migration',
        'render_export_import_page',
        'dashicons-migrate',
        26
    );
});

// Render the admin page for export/import
function render_export_import_page()
{
?>
    <div class="wrap">
        <h1>Export/Import</h1>

        <!-- Export Form -->
        <form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
            <h2>Export Post Type Data</h2>
            <label for="post_type">Select Post Type:</label>
            <select name="post_type" id="post_type" required>
                <?php
                // $post_types = get_post_types(['public' => true], 'objects');
                // foreach ($post_types as $post_type_key => $post_type_object) {
                //     echo "<option value='{$post_type_key}'>{$post_type_object->labels->name}</option>";
                // }
                $post_types = get_posts([
                    'post_type' => ['acf-post-type', 'acf-custom-post-type'], // Include both post types
                    'posts_per_page' => -1, // Fetch all posts
                ]);

                foreach ($post_types as $post_type) {
                    echo "<option value='{$post_type->ID}'>{$post_type->post_title}</option>";
                }
                ?>
            </select>
            <input type="hidden" name="action" value="export_post_type_data">
            <?php wp_nonce_field('export_post_type_nonce', 'export_nonce'); ?>
            <button type="submit" class="button-primary">Export</button>
        </form>

        <hr>

        <!-- Import Form -->
        <form method="POST" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
            <h2>Import Post Type Data</h2>
            <?php wp_nonce_field('import_post_type_nonce', 'import_nonce'); ?>
            <input type="file" name="import_file" accept=".json" required>
            <input type="hidden" name="action" value="import_post_type_data">
            <button type="submit" class="button-primary">Import</button>
        </form>
    </div>
<?php
}

// Handle Export
add_action('admin_post_export_post_type_data', function () {
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 403);
    }

    // Verify nonce
    if (!isset($_POST['export_nonce']) || !wp_verify_nonce($_POST['export_nonce'], 'export_post_type_nonce')) {
        wp_die('Invalid nonce.', 403);
    }

    $post_id = intval($_POST['post_type']);
    if (empty($post_id)) {
        wp_die('Invalid post selected.');
    }

    // Get the selected post
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, ['acf-post-type', 'acf-custom-post-type'])) {
        wp_die('Invalid post selected.');
    }

    // Collect data for export
    $export_data = [
        'post' => $post,
        'meta' => get_post_meta($post->ID),
        'taxonomies' => [],
        'acf_field_groups' => [],
        'woocommerce_products' => [],
    ];

    // Fetch WooCommerce products with the same title as the post
    $products = wc_get_products([
        'status' => 'publish',
        'limit' => -1,
        'search' => $post->post_title, // Search products with the same title
    ]);

    foreach ($products as $product) {
        if ($product->get_name() === $post->post_title) { // Ensure exact match
            $export_data['woocommerce_products'][] = [
                'product' => $product->get_data(),
                'meta' => get_post_meta($product->get_id()),
                'taxonomies' => [],
            ];

            // Fetch and add product taxonomies
            $product_taxonomies = get_object_taxonomies('product', 'objects');
            foreach ($product_taxonomies as $taxonomy) {
                $terms = wp_get_object_terms($product->get_id(), $taxonomy->name);
                $export_data['woocommerce_products'][count($export_data['woocommerce_products']) - 1]['taxonomies'][$taxonomy->name] = wp_list_pluck($terms, 'slug');
            }
        }
    }

    // echo '<pre>';
    // print_r($export_data);
    // echo '</pre>';
    // exit;

    // Fetch and add taxonomies
    $taxonomies = get_object_taxonomies($post->post_type, 'objects');
    foreach ($taxonomies as $taxonomy) {
        $terms = wp_get_object_terms($post->ID, $taxonomy->name);
        $export_data['taxonomies'][$taxonomy->name] = wp_list_pluck($terms, 'slug'); // Export slugs only
    }

    // Handle special case for 'acf-post-type'
    if (!empty($post->post_content)) {
        if ($post->post_type === 'acf-custom-post-type') {
            $post_content_data = json_decode($post->post_content);
            $post_content_post_type = $post_content_data->post_type;
        } else {
            $post_content_data = maybe_unserialize($post->post_content);
            $post_content_post_type = $post_content_data['post_type'];
        }

        if (!empty($post_content_post_type)) {
            $related_posts = get_posts([
                'post_type' => $post_content_post_type,
                'posts_per_page' => -1,
            ]);

            $export_data['related_posts'] = [];
            foreach ($related_posts as $related_post) {
                $export_data['related_posts'][] = [
                    'post' => $related_post,
                    'meta' => get_post_meta($related_post->ID),
                ];
            }
        }
    }

    // Fetch ACF field groups
    if (function_exists('acf_get_field_groups')) {
        $acf_field_groups = acf_get_field_groups();

        foreach ($acf_field_groups as $field_group) {
            // Export each field group with its fields
            $fields = function_exists('acf_get_fields') ? acf_get_fields($field_group['key']) : [];
            $export_data['acf_field_groups'][] = [
                'field_group' => $field_group,
                'fields' => $fields,
            ];
        }
    }

    // Output JSON for download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="export-' . $post->post_type . '-' . $post->post_title . '.json"');
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit;
});

// Handle Import
add_action('admin_post_import_post_type_data', function () {
    global $wpdb;

    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.', 403);
    }

    // Verify nonce
    if (!isset($_POST['import_nonce']) || !wp_verify_nonce($_POST['import_nonce'], 'import_post_type_nonce')) {
        wp_die('Invalid nonce.', 403);
    }

    if (empty($_FILES['import_file']['tmp_name'])) {
        wp_die('No file uploaded.', 400);
    }

    // Read and parse the uploaded JSON file
    $file_data = file_get_contents($_FILES['import_file']['tmp_name']);
    $import_data = json_decode($file_data, true);

    // echo '<pre>';
    // print_r($import_data);
    // echo '</pre>';
    // exit;

    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('Invalid JSON file.', 400);
    }

    $id_map = []; // Map of old IDs to new IDs for handling relationships
    $new_site_url = home_url();

    // Insert main post if it doesn't already exist
    $post_array = $import_data['post'];
    $old_post_id = $post_array['ID'] ?? null;

    // Check if a post with the same title already exists
    $existing_post = get_page_by_title($post_array['post_title'], OBJECT, $post_array['post_type']);

    if ($existing_post) {
        $new_post_id = $existing_post->ID;  // If it exists, use the existing post ID
    } else {
        // Ensure a new post is created if it doesn't already exist
        unset($post_array['ID']);  // Remove the ID to create a new post
        $new_post_id = wp_insert_post((array) $post_array, true);

        if (is_wp_error($new_post_id)) {
            wp_die('Failed to import post: ' . $post_array['post_title']);
        }

        // Add a unique meta key to track the post for future imports
        if ($old_post_id) {
            add_post_meta($new_post_id, '_import_unique_key', $old_post_id);
        }
    }

    if ($old_post_id) {
        $id_map[$old_post_id] = $new_post_id;
    }

    // Restore metadata if it doesn't already exist
    if (isset($import_data['meta'])) {
        foreach ($import_data['meta'] as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                $meta_value = maybe_unserialize($meta_value);

                // Skip if the meta key already exists for this post
                if (metadata_exists('post', $new_post_id, $meta_key)) {
                    continue;
                }

                // Check and replace old ID in the meta value if needed
                if (is_numeric($meta_value) && isset($id_map[$meta_value])) {
                    $meta_value = $id_map[$meta_value];
                }

                // Insert the meta data
                update_post_meta($new_post_id, $meta_key, $meta_value);
            }
        }
    }

    // Restore taxonomies if they don't already exist
    if (isset($import_data['taxonomies'])) {
        foreach ($import_data['taxonomies'] as $taxonomy => $terms) {
            $existing_terms = wp_get_object_terms($new_post_id, $taxonomy, ['fields' => 'slugs']);
            foreach ($terms as $term_slug) {
                // Only assign term if it's not already assigned
                if (!in_array($term_slug, $existing_terms)) {
                    $term = get_term_by('slug', $term_slug, $taxonomy);
                    if ($term) {
                        wp_set_object_terms($new_post_id, $term->term_id, $taxonomy, true);
                    }
                }
            }
        }
    }

    // Handle related posts for 'acf-post-type' with validation
    if (isset($import_data['related_posts'])) {
        foreach ($import_data['related_posts'] as $related_post_data) {
            $related_post_array = $related_post_data['post'];
            $related_old_id = $related_post_array['ID'] ?? null;

            // Check if the related post already exists by title
            $existing_related_post = get_page_by_title($related_post_array['post_title'], OBJECT, $related_post_array['post_type']);

            if ($existing_related_post) {
                $related_post_id = $existing_related_post->ID;  // Use existing related post ID
            } else {
                unset($related_post_array['ID']);
                $related_post_id = wp_insert_post((array) $related_post_array, true);

                if (is_wp_error($related_post_id)) {
                    continue;  // Skip this related post if there's an error
                }

                // Add unique meta key to track the related post
                if ($related_old_id) {
                    add_post_meta($related_post_id, '_import_unique_key', $related_old_id);
                }
            }

            // Restore metadata for related posts if it doesn't already exist
            if (isset($related_post_data['meta'])) {
                foreach ($related_post_data['meta'] as $meta_key => $meta_values) {
                    foreach ($meta_values as $meta_value) {
                        $meta_value = maybe_unserialize($meta_value);
                        if (is_numeric($meta_value) && isset($id_map[$meta_value])) {
                            $meta_value = $id_map[$meta_value];
                        }

                        // Only insert meta data if it doesn't already exist
                        if (!metadata_exists('post', $related_post_id, $meta_key)) {
                            update_post_meta($related_post_id, $meta_key, $meta_value);
                        }
                    }
                }
            }

            if ($related_old_id) {
                $id_map[$related_old_id] = $related_post_id;
            }
        }
    }

    // Update GUID and post parent after inserting related posts
    foreach ($id_map as $old_id => $new_id) {
        $old_post = get_post($new_id);

        // Update `post_parent` if necessary
        if ($old_post && isset($old_post->post_parent) && isset($id_map[$old_post->post_parent])) {
            wp_update_post([
                'ID' => $new_id,
                'post_parent' => $id_map[$old_post->post_parent],
            ]);
        }

        // Update metadata for the post
        $old_post_meta = get_post_meta($new_id);
        foreach ($old_post_meta as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                $meta_value = maybe_unserialize($meta_value);
                if (is_numeric($meta_value) && isset($id_map[$meta_value])) {
                    $meta_value = $id_map[$meta_value];
                    update_post_meta($new_id, $meta_key, $meta_value);
                }
            }
        }

        // Update the GUID
        $old_guid = $old_post->guid ?? null;
        if ($old_guid) {
            // Parse and update the GUID as needed
            $parsed_url = parse_url($old_guid);
            $path = $parsed_url['path'] ?? '';
            $query = $parsed_url['query'] ?? '';

            $new_guid = $new_site_url;

            if ($query) {
                parse_str($query, $query_vars);
                if (isset($query_vars['p'])) {
                    $query_vars['p'] = $new_id;
                }
                $new_query = http_build_query($query_vars, '', '&', PHP_QUERY_RFC3986);
                $new_guid = "{$new_site_url}/?{$new_query}";
            } else {
                $new_guid = "{$new_site_url}{$path}";
            }

            $wpdb->update(
                $wpdb->posts,
                ['guid' => $new_guid],
                ['ID' => $new_id],
                ['%s'],
                ['%d']
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Updated GUID for post ID: $new_id from $old_guid to $new_guid");
            }
        }
    }

    // Import ACF field groups if needed
    if (isset($import_data['acf_field_groups'])) {
        foreach ($import_data['acf_field_groups'] as $acf_data) {
            $field_group = $acf_data['field_group'];
            $fields = $acf_data['fields'];

            // Remove old ID to ensure a new field group is created
            unset($field_group['ID']);

            ob_start();

            // Check if the field group already exists by name
            $existing_field_group = get_page_by_title($field_group['title'], OBJECT, 'acf-field-group');
            if ($existing_field_group) {
                $field_group_id = $existing_field_group->ID;
                $existing_group_data = acf_get_field_group($field_group_id);

                // Compare and update the field group if there are changes
                if ($existing_group_data && $field_group !== $existing_group_data) {
                    acf_update_field_group(array_merge($existing_group_data, $field_group));
                }
            } else {
                // Import the field group if it doesn't exist
                if (function_exists('acf_import_field_group')) {
                    $field_group_id = acf_import_field_group($field_group);
                    $field_group_id = $field_group_id['ID'] ?? null;

                    if (!is_wp_error($field_group_id)) {
                        // Map the old field group ID to the new one
                        $old_field_group_id = $acf_data['field_group']['ID'];
                        $id_map[$old_field_group_id] = $field_group_id;
                    }
                }
            }

            if ($field_group_id) {
                foreach ($fields as $field) {
                    // Check if the field already exists by key
                    $existing_field = get_field_object($field['key']);
                    if ($existing_field) {
                        // Compare and update the field if there are changes
                        if ($field !== $existing_field) {
                            acf_update_field(array_merge($existing_field, $field));
                        }
                    } else {
                        // Remove old field ID to ensure a new field is created
                        unset($field['ID']);

                        // Update the parent property to the new field group ID
                        if (isset($field['parent']) && $field['parent'] == $old_field_group_id) {
                            $field['parent'] = $field_group_id;
                        }

                        // Import the field
                        if (function_exists('acf_update_field')) {
                            acf_update_field($field);
                        }
                    }
                }
            }

            ob_end_clean();
        }
    }

    // Import WooCommerce products
    if (isset($import_data['woocommerce_products'])) {
        foreach ($import_data['woocommerce_products'] as $product_data) {
            $product_array = $product_data['product'];

            // Get product by SKU or title
            $existing_product = null;

            // Check by SKU
            if (!empty($product_array['sku'])) {
                $product_id = wc_get_product_id_by_sku($product_array['sku']);
                if ($product_id) {
                    $existing_product = wc_get_product($product_id);
                }
            }

            // Fallback to search by title
            if (!$existing_product && !empty($product_array['name'])) {
                $query_args = [
                    'post_type' => 'product',
                    'title' => $product_array['name'],
                    'posts_per_page' => 1,
                    'post_status' => 'publish',
                ];
                $product_query = new WP_Query($query_args);
                if ($product_query->have_posts()) {
                    $existing_product = wc_get_product($product_query->posts[0]->ID);
                }
            }

            // Update existing product or create a new one
            if ($existing_product) {
                $product_id = $existing_product->get_id();
                wp_update_post(array_merge(['ID' => $product_id], $product_array));
            } else {
                $product = new WC_Product_Simple();
                $product->set_name($product_array['name']);
                $product->set_sku($product_array['sku']);
                $product->set_price($product_array['price']);
                $product->save();
                $product_id = $product->get_id();
            }

            // Restore metadata
            if (isset($product_data['meta'])) {
                foreach ($product_data['meta'] as $meta_key => $meta_values) {
                    foreach ($meta_values as $meta_value) {
                        update_post_meta($product_id, $meta_key, maybe_unserialize($meta_value));
                    }
                }
            }

            // Restore taxonomies
            if (isset($product_data['taxonomies'])) {
                foreach ($product_data['taxonomies'] as $taxonomy => $terms) {
                    wp_set_object_terms($product_id, $terms, $taxonomy);
                }
            }
        }
    }

    // Redirect to admin page with success message
    wp_redirect(admin_url('admin.php?page=course-migration&import=success'));
    exit;
});
