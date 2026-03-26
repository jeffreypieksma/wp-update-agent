<?php
/**
 * Core Handler class for WordPress core and language operations
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Core_Handler
 * 
 * Handles WordPress core and language update operations
 */
class WP_Update_Agent_Core_Handler {
    
    /**
     * Load required WordPress admin files
     */
    private static function load_admin_files() {
        require_once ABSPATH . 'wp-admin/includes/update.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        
        // Ensure we have filesystem access
        WP_Filesystem();
    }
    
    /**
     * Check if core update is available
     *
     * @return array
     */
    public static function core_check() {
        self::load_admin_files();
        
        // Force check for updates
        wp_version_check();
        
        $update_core = get_site_transient('update_core');
        $current_version = get_bloginfo('version');
        
        $update_available = false;
        $new_version = null;
        $update_type = null;
        
        if (!empty($update_core->updates)) {
            foreach ($update_core->updates as $update) {
                if ($update->response === 'upgrade') {
                    $update_available = true;
                    $new_version = $update->version;
                    
                    // Determine update type
                    $current_parts = explode('.', $current_version);
                    $new_parts = explode('.', $new_version);
                    
                    if ($current_parts[0] !== $new_parts[0]) {
                        $update_type = 'major';
                    } elseif ($current_parts[1] !== $new_parts[1]) {
                        $update_type = 'minor';
                    } else {
                        $update_type = 'security';
                    }
                    
                    break;
                }
            }
        }
        
        WP_Update_Agent_Logger::log_action('core_check', 'success', array(
            'current_version' => $current_version,
            'update_available' => $update_available,
            'new_version' => $new_version,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'core_check',
            'current_version' => $current_version,
            'update_available' => $update_available,
            'new_version' => $new_version,
            'update_type' => $update_type,
            'logs' => $update_available 
                ? sprintf('Update available: %s → %s (%s)', $current_version, $new_version, $update_type)
                : sprintf('WordPress %s is up to date', $current_version),
        );
    }
    
    /**
     * Update WordPress core
     *
     * @param string|null $version Specific version to update to (optional)
     * @return array
     */
    public static function core_update($version = null) {
        self::load_admin_files();
        
        // Force check for updates
        wp_version_check();
        
        $update_core = get_site_transient('update_core');
        $current_version = get_bloginfo('version');
        
        if (empty($update_core->updates)) {
            WP_Update_Agent_Logger::log_action('core_update', 'error', array(
                'error' => 'No update information available',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'core_update',
                'logs' => 'No update information available. Try again later.',
            );
        }
        
        // Find the appropriate update
        $target_update = null;
        
        foreach ($update_core->updates as $update) {
            if ($update->response === 'upgrade') {
                if ($version === null || $update->version === $version) {
                    $target_update = $update;
                    break;
                }
            }
        }
        
        if (!$target_update) {
            // If a specific version was requested but not found, that's an error
            if ($version !== null) {
                WP_Update_Agent_Logger::log_action('core_update', 'error', array(
                    'error' => 'Requested version not available',
                    'requested_version' => $version,
                ));
                
                return array(
                    'status' => 'error',
                    'action' => 'core_update',
                    'logs' => sprintf('Version %s is not available for update', $version),
                );
            }
            
            // No update needed - this is not an error, the site is already up to date
            WP_Update_Agent_Logger::log_action('core_update', 'success', array(
                'message' => 'Already up to date',
                'current_version' => $current_version,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'core_update',
                'logs' => sprintf('WordPress %s is already the latest version', $current_version),
            );
        }
        
        // Perform the update
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Core_Upgrader($skin);
        
        // Allow automatic updates for this request
        add_filter('allow_major_auto_core_updates', '__return_true');
        add_filter('allow_minor_auto_core_updates', '__return_true');
        
        $result = $upgrader->upgrade($target_update);
        
        // Remove filters
        remove_filter('allow_major_auto_core_updates', '__return_true');
        remove_filter('allow_minor_auto_core_updates', '__return_true');
        
        if (is_wp_error($result)) {
            WP_Update_Agent_Logger::log_action('core_update', 'error', array(
                'error' => $result->get_error_message(),
                'target_version' => $target_update->version,
            ));
            
            return array(
                'status' => 'error',
                'action' => 'core_update',
                'logs' => sprintf('Failed to update WordPress: %s', $result->get_error_message()),
            );
        }
        
        if ($result === false) {
            WP_Update_Agent_Logger::log_action('core_update', 'error', array(
                'error' => 'Update failed',
                'target_version' => $target_update->version,
            ));
            
            return array(
                'status' => 'error',
                'action' => 'core_update',
                'logs' => 'Failed to update WordPress. Check file permissions.',
            );
        }
        
        // Clear update transient
        delete_site_transient('update_core');
        wp_version_check();
        
        $new_version = get_bloginfo('version');
        
        WP_Update_Agent_Logger::log_action('core_update', 'success', array(
            'old_version' => $current_version,
            'new_version' => $new_version,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'core_update',
            'old_version' => $current_version,
            'new_version' => $new_version,
            'logs' => sprintf('Successfully updated WordPress from %s to %s', $current_version, $new_version),
        );
    }
    
    /**
     * Update language files
     *
     * @return array
     */
    public static function language_update() {
        self::load_admin_files();
        
        // Force check for language updates
        wp_update_themes();
        wp_update_plugins();
        
        $translations = wp_get_translation_updates();
        
        if (empty($translations)) {
            WP_Update_Agent_Logger::log_action('language_update', 'success', array(
                'message' => 'No language updates available',
            ));
            
            return array(
                'status' => 'success',
                'action' => 'language_update',
                'updated' => array(),
                'failed' => array(),
                'logs' => 'No language updates available',
            );
        }
        
        // Perform language updates
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Language_Pack_Upgrader($skin);
        
        $result = $upgrader->bulk_upgrade($translations);
        
        $updated = array();
        $failed = array();
        $logs = array();
        
        if (is_array($result)) {
            foreach ($result as $translation => $update_result) {
                $translation_info = isset($translations[$translation]) 
                    ? $translations[$translation]->slug 
                    : $translation;
                
                if ($update_result === true || !is_wp_error($update_result)) {
                    $updated[] = $translation_info;
                    $logs[] = sprintf('Updated: %s', $translation_info);
                } else {
                    $failed[] = $translation_info;
                    $error_message = is_wp_error($update_result) 
                        ? $update_result->get_error_message() 
                        : 'Unknown error';
                    $logs[] = sprintf('Failed: %s - %s', $translation_info, $error_message);
                }
            }
        }
        
        $status = empty($failed) ? 'success' : (empty($updated) ? 'error' : 'partial');
        
        WP_Update_Agent_Logger::log_action('language_update', $status, array(
            'updated' => $updated,
            'failed' => $failed,
        ));
        
        return array(
            'status' => $status,
            'action' => 'language_update',
            'updated' => $updated,
            'failed' => $failed,
            'logs' => empty($logs) ? 'Language update completed' : implode("\n", $logs),
        );
    }
    
    /**
     * Get system status information
     *
     * @return array
     */
    public static function system_status() {
        global $wpdb;
        
        $status = array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'is_multisite' => is_multisite(),
            'locale' => get_locale(),
            'timezone' => wp_timezone_string(),
            'memory_limit' => WP_MEMORY_LIMIT,
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'active_theme' => wp_get_theme()->get('Name'),
            'active_plugins_count' => count(get_option('active_plugins', array())),
        );
        
        WP_Update_Agent_Logger::log_action('system_status', 'success', array());
        
        return array(
            'status' => 'success',
            'action' => 'system_status',
            'data' => $status,
            'logs' => 'System status retrieved',
        );
    }
}
