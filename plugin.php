<?php

/**
 * Plugin Name: Media Library Skeleton Case Bulk Renamer
 * Description: Renames all WordPress media files and their generated sizes to skeleton case, including the actual files on the server.
 * Version: 1.7
 * Author: Nathan Donnelly
 * Author URI: https://www.nathandonnelly.com/
 */

// Convert string to skeleton case
function to_skeleton_case($string)
{
  $string = strtolower($string);
  $string = preg_replace('/[^a-z0-9]+/', '-', $string);
  $string = trim($string, '-');
  return $string;
}

// Rename existing media files and all their sizes
function rename_existing_media_with_sizes()
{
  $args = [
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
  ];

  $media_files = get_posts($args);
  $results = ['success' => [], 'errors' => []];

  foreach ($media_files as $media_file) {
    $file_path = get_attached_file($media_file->ID);
    $file_info = pathinfo($file_path);

    if (!$file_path || !file_exists($file_path)) {
      $results['errors'][] = "File not found: $file_path";
      continue;
    }

    $new_filename = to_skeleton_case($file_info['filename']);
    $new_file_path = $file_info['dirname'] . '/' . $new_filename . '.' . $file_info['extension'];

    // Handle the main file
    if (strtolower($file_path) === strtolower($new_file_path)) {
      // Case-only renaming
      $temp_file_path = $file_info['dirname'] . '/' . uniqid('temp_', true) . '.' . $file_info['extension'];
      if (rename($file_path, $temp_file_path) && rename($temp_file_path, $new_file_path)) {
        update_attached_file($media_file->ID, $new_file_path);
        $results['success'][] = "Renamed (case-only): $file_path -> $new_file_path";
      } else {
        $results['errors'][] = "Failed to rename: $file_path";
        continue;
      }
    } elseif (file_exists($new_file_path)) {
      $results['errors'][] = "Target file already exists: $new_file_path";
      continue;
    } else {
      if (rename($file_path, $new_file_path)) {
        update_attached_file($media_file->ID, $new_file_path);
        $results['success'][] = "Renamed: $file_path -> $new_file_path";
      } else {
        $results['errors'][] = "Failed to rename: $file_path";
        continue;
      }
    }

    // Handle resized images in metadata
    $metadata = wp_get_attachment_metadata($media_file->ID);
    if ($metadata && !empty($metadata['sizes'])) {
      foreach ($metadata['sizes'] as $size_key => $size_data) {
        if (isset($size_data['file'])) {
          $old_size_filename = $size_data['file'];
          $old_size_info = pathinfo($old_size_filename);

          $new_size_filename = to_skeleton_case($old_size_info['filename']) . '.' . $old_size_info['extension'];

          $old_size_path = $file_info['dirname'] . '/' . $old_size_filename;
          $new_size_path = $file_info['dirname'] . '/' . $new_size_filename;

          // Rename the file for the size
          if (strtolower($old_size_path) === strtolower($new_size_path)) {
            $temp_size_path = $file_info['dirname'] . '/' . uniqid('temp_', true) . '.' . $old_size_info['extension'];
            if (rename($old_size_path, $temp_size_path) && rename($temp_size_path, $new_size_path)) {
              $results['success'][] = "Renamed (case-only): $old_size_path -> $new_size_path";
            } else {
              $results['errors'][] = "Failed to rename: $old_size_path";
              continue;
            }
          } elseif (file_exists($new_size_path)) {
            $results['errors'][] = "Target file already exists: $new_size_path";
            continue;
          } else {
            if (rename($old_size_path, $new_size_path)) {
              $results['success'][] = "Renamed: $old_size_path -> $new_size_path";
            } else {
              $results['errors'][] = "Failed to rename: $old_size_path";
              continue;
            }
          }

          // Update the metadata for the size
          $metadata['sizes'][$size_key]['file'] = $new_size_filename;
        }
      }

      // Save the updated metadata
      wp_update_attachment_metadata($media_file->ID, $metadata);
    }
  }

  return $results;
}

// Admin menu for renaming
add_action('admin_menu', function () {
  add_submenu_page(
    'tools.php',
    'Rename Media to Skeleton Case',
    'Rename Media',
    'manage_options',
    'rename-media-skeleton-case',
    function () {
      if (isset($_POST['rename_media_submit'])) {
        $results = rename_existing_media_with_sizes();
        echo '<div class="wrap">';
        echo '<h1>Rename Media Results</h1>';

        // Display success messages
        if (!empty($results['success'])) {
          echo '<h2>Successes</h2><ul style="color: green;">';
          foreach ($results['success'] as $success) {
            echo "<li>$success</li>";
          }
          echo '</ul>';
        }

        // Display error messages
        if (!empty($results['errors'])) {
          echo '<h2>Errors</h2><ul style="color: red;">';
          foreach ($results['errors'] as $error) {
            echo "<li>$error</li>";
          }
          echo '</ul>';
        }

        echo '</div>';
      } else {
        echo '<div class="wrap">';
        echo '<h1>Rename Media to Skeleton Case</h1>';
        echo '<form method="post">';
        echo '<p>Click the button below to rename all existing media files to skeleton case. Any successes or errors will be displayed here.</p>';
        echo '<input type="submit" name="rename_media_submit" class="button button-primary" value="Rename Files">';
        echo '</form>';
        echo '</div>';
      }
    }
  );
});
