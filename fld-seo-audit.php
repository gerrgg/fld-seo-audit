<?php
/*
Plugin Name: Floodlight SEO Audit
Description: A WordPress plugin to audit SEO performance of your site.
Version: 1.0
Author: Gregory Bastianelli
Author URI: https://floodlight.design/
License: GPL2
*/

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for the plugin
define( 'FLOODLIGHT_SEO_AUDIT_VERSION', '1.0' );
define( 'FLOODLIGHT_SEO_AUDIT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
$GLOBALS['base_url'] = sprintf('%s://%s', $_SERVER['REQUEST_SCHEME'], $_SERVER['SERVER_NAME']);


// Hook for adding admin menus
add_action( 'admin_menu', 'floodlight_seo_audit_add_admin_menu' );

// Hook for plugin activation
register_activation_hook( __FILE__, 'floodlight_seo_audit_activate' );

// Hook for plugin deactivation
register_deactivation_hook( __FILE__, 'floodlight_seo_audit_deactivate' );

add_action('admin_enqueue_scripts', 'fld_seo_audit_enqueue_scripts');

add_action('wp_ajax_fld_seo_audit_update_alt_text', 'fld_seo_audit_update_alt_text');
add_action('wp_ajax_fld_seo_audit_export_as_csv', 'fld_seo_audit_export_as_csv');

function fld_seo_audit_enqueue_scripts() {
  // Register and enqueue the script
  wp_enqueue_script(
      'update-alt-text-js', // Handle for the script
      plugin_dir_url(__FILE__) . 'js/update-alt-text.js', // URL to the script file
      array('jquery'), // Dependencies (optional)
      time(), // Version number (optional)
      true // Load script in the footer (optional)
  );

  // Localize the script with data (optional)
  wp_localize_script('update-alt-text-js', 'updateAltText', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('update-alt-text')
  ));
}

/**
 * Function to add a menu page for the plugin
 */
function floodlight_seo_audit_add_admin_menu() {
    add_menu_page(
        'Floodlight SEO Audit',
        'SEO Audit',
        'manage_options',
        'floodlight-seo-audit',
        'floodlight_seo_audit_admin_page',
        'dashicons-chart-bar',
        6
    );
}

/**
 * Display the plugin's admin page
 */
function floodlight_seo_audit_admin_page() {
    // Check if user is allowed to manage options
    if (!current_user_can('manage_options')) {
      return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fld_seo_audit_post_types'])) {
      // Sanitize and save the selected post types
      $selected_post_types = array_map('sanitize_text_field', $_POST['fld_seo_audit_post_types']);
      update_option('fld_seo_audit_post_types', $selected_post_types);
      echo '<div class="updated"><p>Post types saved successfully.</p></div>';
    }

    // $acf_images = get_images_from_all_acf_wysiwyg();
    $post_types = get_post_types(array('public' => true), 'objects');
    $saved_post_types = get_option('fld_seo_audit_post_types', array());
    $images = get_images_from_all_acf_wysiwyg($saved_post_types);

    ?>
    <div id="fld-seo-audit" class="wrap">
      <h1>Floodlight SEO Audit</h1>
      <p>Welcome to the Floodlight SEO Audit plugin. Here you can perform SEO audits and view reports.</p>
      <?php
        output_audit_results($images);
        output_post_type_settings($post_types, $saved_post_types);
      ?>
    </div>
  <?php
}

function output_audit_results($images = false){
  if( $images ){
    echo '<hr />';
    echo '<h2>Results ('. sizeof($images) .')</h2>';
    foreach ($images as $image) {
      echo '<div style="display: flex; gap: 1rem; margin-bottom: 1rem">';
        printf('<a target="_blank" href="%s/wp-admin/upload.php?item=%s"><img src="%s" width="65" height="65" /></a>', $GLOBALS['base_url'], $image['id'], esc_url($image['image_src']));
        echo '<div>';
          // printf('<p style="margin: 0 0 0.5rem;">Page URL: <a href="%s">%s</a></p>', esc_url($image['post_url']), esc_url($image['post_url']));
          printf('<p style="margin: 0 0 0.25rem;"><b>Edit URL:</b> <a  target="_blank" href="%s">%s</a></p>', esc_url($image['edit_url']), esc_url($image['edit_url']));
          // output_audit_edit_alt_text_form($image);
          output_audit_view_alt_text_form($image);
        echo '</div>';
      echo '</div>';
    }
    
    printf('<button id="export-results-as-csv" class="button-secondary" data-json=\'%s\' style="margin: 0rem 0 1rem">Export as CSV</button>', json_encode($images));
  }
}

function output_audit_edit_alt_text_form($image){
  echo '<form class="fld-submit-alt-text-update" style="display: flex; gap: 0.25rem; align-items: center;">';
    printf('Alt Text: <input type="text" name="alt_text" value="%s" style="margin: 0;"></p>', esc_html($image['alt_text']));
    printf('<input type="hidden" name="image_id" value="%s" />', $image['id']);
    printf('<button class="button-secondary">Update</button>');
  echo '</form>';
}

function output_audit_view_alt_text_form($image){
  printf('<p style="margin: 0;"><b>Alt Text:</b> %s</p>', esc_html($image['alt_text']));
}

function output_post_type_settings($post_types, $saved_post_types){
  echo '<hr />';
  echo '<h2>Post Types to Audit</h2>';
  echo '<form method="post">';
    echo '<table class="form-table">';
      echo '<tbody>';
      foreach ($post_types as $post_type) {
        $checked = in_array($post_type->name, $saved_post_types) ? 'checked' : '';
        if( $post_type->name !== 'attachment' ){
          echo '<tr>';
          echo '<th scope="row">' . esc_html($post_type->labels->name) . '</th>';
          echo '<td><input type="checkbox" name="fld_seo_audit_post_types[]" value="' . esc_attr($post_type->name) . '" ' . $checked . '></td>';
          echo '</tr>';
        }
      }
      echo '</tbody>';
    echo '</table>';
    echo '<p class="submit"><input type="submit" class="button-primary" value="Save Changes"></p>';
  echo '</form>';
}

/**
 * Function to execute on plugin activation
 */
function floodlight_seo_audit_activate() {
    // Add code here to set up default options or database tables
}

/**
 * Function to execute on plugin deactivation
 */
function floodlight_seo_audit_deactivate() {
    // Add code here to clean up resources or data
}

// Custom function to recursively get images from ACF fields, including repeaters
function get_images_from_acf_fields($fields, $post_id, $post_url) {
  $images_data = array();

  foreach ($fields as $field_key => $field) {

    // Error handling: Check if field type is set
    if (!isset($field['type'])) {
      error_log("Field type not set for field key: $field_key");
      continue;
    }

      // Check if the field is a WYSIWYG field
      if ($field['type'] === 'wysiwyg') {
          // Get the content from the ACF WYSIWYG field
          $content = get_field($field_key, $post_id);

          // Check if content exists
          if ($content) {
              // Parse the content for images
              preg_match_all('/<img[^>]+>/i', $content, $img_tags);

              foreach ($img_tags[0] as $img_tag) {
                  // Extract src attribute
                  preg_match('/src="([^"]*)"/i', $img_tag, $src_match);
                  $src = isset($src_match[1]) ? $src_match[1] : '';

                  // Extract alt attribute
                  preg_match('/alt="([^"]*)"/i', $img_tag, $alt_match);
                  $alt = isset($alt_match[1]) ? $alt_match[1] : '';

                  // Store image data
                  if ($src) {
                      $images_data[] = array(
                        'id'        =>  get_image_id_by_url_multisite($src),
                        'alt_text'  => $alt,
                        'edit_url'  => sprintf('%s/wp-admin/post.php?post=%s&action=edit', $GLOBALS['base_url'], $post_id),
                        'image_src' => $src,
                        'post_url'  => $post_url,
                      );
                  }
              }
          }
      }

      // If the field is a repeater, loop through its rows and recursively call the function
      if ($field['type'] === 'repeater') {
          $rows = get_field($field_key, $post_id);
          if ($rows) {
              foreach ($rows as $row) {
                  $images_data = array_merge($images_data, get_images_from_acf_fields($row, $post_id, $post_url));
              }
          }
      }
  }

  return $images_data;
}

// Main function to get all images from ACF WYSIWYG fields in posts
function get_images_from_all_acf_wysiwyg($saved_post_types = ['post']) {
  // Define query parameters to fetch all posts
  $args = array(
      'post_type'      => $saved_post_types, 
      'posts_per_page' => -1, 
      'post_status'    => 'publish',
  );

  // Create a new WP_Query object
  $query = new WP_Query($args);

  // Array to store image data
  $all_images_data = array();

  // Loop through each post
  if ($query->have_posts()) {
      while ($query->have_posts()) {
          $query->the_post();

          // Get the post ID and URL
          $post_id = get_the_ID();
          $post_url = get_permalink($post_id);

          // Retrieve all ACF fields for the current post
          $fields = get_field_objects($post_id);

          if ($fields) {
              // Get images data recursively
              $all_images_data = array_merge($all_images_data, get_images_from_acf_fields($fields, $post_id, $post_url));
          }
      }
      // Restore original Post Data
      wp_reset_postdata();
  }

  return $all_images_data;
}

function get_image_id_by_url_multisite($image_url) {
  global $wpdb;

  // Remove the query string from the URL if any (e.g., ?resize=600%2C400)
  $image_url = strtok($image_url, '?');

  // Extract the file name from the URL
  $image_name = basename($image_url);

  $clean_name = remove_last_size_pattern_from_url($image_url);

  // Get the current site ID
  $current_blog_id = get_current_blog_id();

  // Adjust query to consider multisite database prefix
  $table_prefix = $wpdb->get_blog_prefix($current_blog_id);

  $query = $wpdb->prepare("SELECT ID FROM {$table_prefix}posts WHERE post_name = %s AND post_type = 'attachment'", $clean_name);

  // Get the result from the query
  $image_id = $wpdb->get_var($query);

  // If no result, try searching with sanitized name
  if (empty($image_id)) {
      $image_name_sanitized = sanitize_title($image_name);
      $query_sanitized = $wpdb->prepare("SELECT ID FROM {$table_prefix}posts WHERE post_name = %s AND post_type = 'attachment'", $image_name_sanitized);
      $image_id = $wpdb->get_var($query_sanitized);
  }

  return $image_id;
}

/**
 * Remove the last size-related pattern from a URL and return the base file name.
 *
 * @param string $url The URL containing the file name with size-related patterns.
 * @return string The base file name without the last size-related pattern.
 */
function remove_last_size_pattern_from_url($url) {
  // Extract the file name from the URL
  $path = parse_url($url, PHP_URL_PATH);
  $filename = basename($path);

  // Remove the last size-related pattern like '-150x150' or '-1-150x150'
  $cleaned_filename = preg_replace('/-(?:\d+-)?\d+x\d+(?=\.[^.]*$)/', '', $filename);

  // Remove the file extension
  $cleaned_filename = pathinfo($cleaned_filename, PATHINFO_FILENAME);

  return $cleaned_filename;
}

function fld_seo_audit_update_alt_text() {
  check_ajax_referer('update-alt-text', 'nonce');

  $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
  $new_alt_text = isset($_POST['alt_text']) ? sanitize_text_field($_POST['alt_text']) : '';

  if ($image_id && $new_alt_text) {
      // Update the alt text
      update_post_meta($image_id, '_wp_attachment_image_alt', $new_alt_text);
      wp_send_json_success(array('message' => 'Alt text updated successfully.'));
  } else {
      wp_send_json_error(array('message' => 'Invalid data.'));
  }

  wp_die();
}

function fld_seo_audit_export_as_csv() {
  check_ajax_referer('update-alt-text', 'nonce');

  // Set the headers to force download
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="floodlight-seo-audit-'.time().'.csv"');

  $headers = [
    'Image ID',
    'Alt Text',
    'Edit URL',
    'Image URL',
    'Page URL',
  ];

  $data = $_POST['csvData'];
  array_unshift($data,  $headers);

  // Create a file pointer connected to the output stream
  $fp = fopen('php://output', 'w');

  // Output the data array to the CSV file
  foreach ($data as $row) {
    fputcsv($fp, $row);
  }

  // Close the file pointer
  fclose($fp);

  wp_die();
}
