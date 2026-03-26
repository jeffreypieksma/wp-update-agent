<?php
/**
 * Theme Handler class for theme operations
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Theme_Handler
 * 
 * Handles all theme-related operations
 */
class WP_Update_Agent_Theme_Handler {
    
    /**
     * Valid theme slug pattern
     */
    const SLUG_PATTERN = '/^[a-z0-9\-]+$/';
    
    /**
     * Validate theme slug
     *
     * @param string $slug
     * @return true|WP_Error
     */
    public static function validate_slug($slug) {
        if (empty($slug)) {
            return new WP_Error(
                'agent_invalid_slug',
                __('Theme slug is required.', 'wp-update-agent'),
                array('status' => 400)
            );
        }
        
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return new WP_Error(
                'agent_invalid_slug',
                __('Invalid theme slug format. Only lowercase letters, numbers, and hyphens are allowed.', 'wp-update-agent'),
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Load required WordPress admin files
     */
    private static function load_admin_files() {
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';
        
        // Ensure we have filesystem access
        WP_Filesystem();
    }
    
    /**
     * Get list of all themes with status
     *
     * @return array
     */
    public static function get_theme_list() {
        self::load_admin_files();
        
        // Force check for updates
        wp_update_themes();
        
        $all_themes = wp_get_themes();
        $update_themes = get_site_transient('update_themes');
        $current_theme = get_stylesheet();
        $parent_theme = get_template();
        
        $themes = array();
        
        foreach ($all_themes as $slug => $theme) {
            // Check if update is available
            $update_available = false;
            $new_version = null;
            
            if (isset($update_themes->response[$slug])) {
                $update_available = true;
                $new_version = $update_themes->response[$slug]['new_version'];
            }
            
            $themes[] = array(
                'slug' => $slug,
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'active' => ($slug === $current_theme),
                'is_parent' => ($slug === $parent_theme && $slug !== $current_theme),
                'parent_theme' => $theme->get('Template') ?: null,
                'update_available' => $update_available,
                'new_version' => $new_version,
                'description' => $theme->get('Description'),
                'author' => $theme->get('Author'),
                'theme_uri' => $theme->get('ThemeURI'),
                'screenshot' => $theme->get_screenshot(),
            );
        }
        
        WP_Update_Agent_Logger::log_action('theme_list', 'success', array(
            'total_themes' => count($themes),
        ));
        
        return array(
            'status' => 'success',
            'action' => 'theme_list',
            'themes' => $themes,
            'current_theme' => $current_theme,
            'logs' => sprintf('Found %d themes', count($themes)),
        );
    }
    
    /**
     * Update a single theme
     *
     * @param string $slug Theme slug
     * @return array
     */
    public static function update_theme($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'update_theme',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        // Check if theme exists
        $theme = wp_get_theme($slug);
        
        if (!$theme->exists()) {
            WP_Update_Agent_Logger::log_action('update_theme', 'error', array(
                'slug' => $slug,
                'error' => 'Theme not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'update_theme',
                'failed' => array($slug),
                'logs' => sprintf('Theme "%s" not found', $slug),
            );
        }
        
        // Check if update is available
        $update_themes = get_site_transient('update_themes');
        
        if (!isset($update_themes->response[$slug])) {
            WP_Update_Agent_Logger::log_action('update_theme', 'error', array(
                'slug' => $slug,
                'error' => 'No update available',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'update_theme',
                'failed' => array($slug),
                'logs' => sprintf('No update available for theme "%s"', $slug),
            );
        }
        
        // Perform the update
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        
        $result = $upgrader->upgrade($slug);
        
        // Check if update was successful - upgrade() returns true on success
        if ($result === true) {
            // Clear update transient
            delete_site_transient('update_themes');
            wp_update_themes();
            
            WP_Update_Agent_Logger::log_action('update_theme', 'success', array(
                'slug' => $slug,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'update_theme',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => sprintf('Successfully updated theme "%s"', $slug),
            );
        }
        
        // Get error message
        $error_message = 'Unknown error';
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } elseif ($result === false || $result === null) {
            $error_message = 'Update failed. The theme may not exist or an error occurred.';
        }
        
        WP_Update_Agent_Logger::log_action('update_theme', 'error', array(
            'slug' => $slug,
            'error' => $error_message,
        ));
        
        return array(
            'status' => 'error',
            'action' => 'update_theme',
            'failed' => array($slug),
            'logs' => sprintf('Failed to update theme "%s": %s', $slug, $error_message),
        );
    }
    
    /**
     * Update all themes with available updates
     *
     * @return array
     */
    public static function update_all_themes() {
        self::load_admin_files();
        
        // Force check for updates
        delete_site_transient('update_themes');
        wp_update_themes();
        
        $update_themes = get_site_transient('update_themes');
        
        if (empty($update_themes->response)) {
            WP_Update_Agent_Logger::log_action('update_all_themes', 'success', array(
                'message' => 'No updates available',
            ));
            
            return array(
                'status' => 'success',
                'action' => 'update_all_themes',
                'updated' => array(),
                'failed' => array(),
                'logs' => 'No theme updates available',
            );
        }
        
        $updated = array();
        $failed = array();
        $logs = array();
        
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        
        foreach ($update_themes->response as $slug => $theme_info) {
            $result = $upgrader->upgrade($slug);
            
            // Check if update was successful - upgrade() returns true on success
            if ($result === true) {
                $updated[] = $slug;
                $logs[] = sprintf('Updated: %s', $slug);
            } else {
                $failed[] = $slug;
                $error_message = 'Unknown error';
                if (is_wp_error($result)) {
                    $error_message = $result->get_error_message();
                } elseif ($result === false || $result === null) {
                    $error_message = 'Update failed';
                }
                $logs[] = sprintf('Failed: %s - %s', $slug, $error_message);
            }
        }
        
        // Clear update transient
        delete_site_transient('update_themes');
        wp_update_themes();
        
        $status = empty($failed) ? 'success' : (empty($updated) ? 'error' : 'partial');
        
        WP_Update_Agent_Logger::log_action('update_all_themes', $status, array(
            'updated' => $updated,
            'failed' => $failed,
        ));
        
        return array(
            'status' => $status,
            'action' => 'update_all_themes',
            'updated' => $updated,
            'failed' => $failed,
            'logs' => implode("\n", $logs),
        );
    }
    
    /**
     * Install a theme from WordPress.org by slug
     *
     * @param string $slug Theme slug
     * @param bool $activate Whether to activate after installation
     * @return array
     */
    public static function install_theme_slug($slug, $activate = false) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'install_theme_slug',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        // Check if theme already exists
        $existing_theme = wp_get_theme($slug);
        if ($existing_theme->exists()) {
            WP_Update_Agent_Logger::log_action('install_theme_slug', 'error', array(
                'slug' => $slug,
                'error' => 'Theme already installed',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_theme_slug',
                'failed' => array($slug),
                'logs' => sprintf('Theme "%s" is already installed', $slug),
            );
        }
        
        // Get theme info from WordPress.org
        $api = themes_api('theme_information', array(
            'slug' => $slug,
            'fields' => array(
                'sections' => false,
                'rating' => false,
                'ratings' => false,
                'downloaded' => false,
                'downloadlink' => true,
                'last_updated' => false,
                'tags' => false,
                'homepage' => false,
            ),
        ));
        
        if (is_wp_error($api)) {
            WP_Update_Agent_Logger::log_action('install_theme_slug', 'error', array(
                'slug' => $slug,
                'error' => $api->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_theme_slug',
                'failed' => array($slug),
                'logs' => sprintf('Could not fetch theme info for "%s": %s', $slug, $api->get_error_message()),
            );
        }
        
        // Install the theme
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Theme_Upgrader($skin);
        
        $result = $upgrader->install($api->download_link);
        
        // Check if installation was successful - install() returns true on success
        if ($result === true) {
            $logs = sprintf('Successfully installed theme "%s"', $slug);
            
            // Activate if requested
            if ($activate) {
                switch_theme($slug);
                $logs .= ' | Theme activated';
            }
            
            WP_Update_Agent_Logger::log_action('install_theme_slug', 'success', array(
                'slug' => $slug,
                'activated' => $activate,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'install_theme_slug',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => $logs,
            );
        }
        
        // Get error message
        $error_message = 'Unknown error';
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } elseif ($result === false || $result === null) {
            $error_message = 'Installation failed. The theme may already be installed.';
        }
        
        WP_Update_Agent_Logger::log_action('install_theme_slug', 'error', array(
            'slug' => $slug,
            'error' => $error_message,
        ));
        
        return array(
            'status' => 'error',
            'action' => 'install_theme_slug',
            'failed' => array($slug),
            'logs' => sprintf('Failed to install theme "%s": %s', $slug, $error_message),
        );
    }
    
    /**
     * Install a theme from uploaded ZIP file
     *
     * @param array $file_data File data from $_FILES or base64 encoded content
     * @param bool $activate Whether to activate after installation
     * @return array
     */
    public static function install_theme_zip($file_data, $activate = false) {
        self::load_admin_files();
        
        $temp_file = null;
        
        try {
            // Handle base64 encoded ZIP
            if (isset($file_data['content']) && isset($file_data['filename'])) {
                // Validate filename
                $filename = sanitize_file_name($file_data['filename']);
                
                if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
                    throw new Exception('Only ZIP files are allowed');
                }
                
                // Decode content
                $content = base64_decode($file_data['content'], true);
                
                if ($content === false) {
                    throw new Exception('Invalid base64 content');
                }
                
                // Check file size
                if (strlen($content) > WP_UPDATE_AGENT_MAX_ZIP_SIZE) {
                    throw new Exception(sprintf('File size exceeds maximum allowed (%d MB)', WP_UPDATE_AGENT_MAX_ZIP_SIZE / 1024 / 1024));
                }
                
                // Create temp file
                $temp_file = wp_tempnam($filename);
                file_put_contents($temp_file, $content);
                
            } else {
                throw new Exception('Invalid file data format');
            }
            
            // Validate ZIP file
            $validation = self::validate_zip_file($temp_file);
            if (is_wp_error($validation)) {
                throw new Exception($validation->get_error_message());
            }
            
            // Install the theme with overwrite enabled for existing themes
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Theme_Upgrader($skin);
            
            // Use overwrite_package to allow updating existing themes
            $result = $upgrader->install($temp_file, array(
                'overwrite_package' => true,
            ));
            
            // Clean up temp file
            if ($temp_file && file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            // Check if installation was successful
            // Note: $upgrader->install() returns true on success, WP_Error on failure, 
            // or null/false if something went wrong without an error
            if ($result === true) {
                // Get installed theme info
                $installed_theme = $upgrader->theme_info();
                $slug = $installed_theme ? $installed_theme->get_stylesheet() : 'unknown';
                
                $logs = sprintf('Successfully installed theme from ZIP');
                
                // Activate if requested
                if ($activate && $installed_theme) {
                    switch_theme($installed_theme->get_stylesheet());
                    $logs .= ' | Theme activated';
                }
                
                WP_Update_Agent_Logger::log_action('install_theme_zip', 'success', array(
                    'slug' => $slug,
                    'activated' => $activate,
                ));
                
                return array(
                    'status' => 'success',
                    'action' => 'install_theme_zip',
                    'updated' => array($slug),
                    'failed' => array(),
                    'logs' => $logs,
                );
            }
            
            // Get error message from skin or result
            $error_message = 'Unknown error';
            if (is_wp_error($result)) {
                $error_message = $result->get_error_message();
            } elseif (isset($skin) && method_exists($skin, 'get_errors') && !empty($skin->get_errors())) {
                $errors = $skin->get_errors();
                if (is_wp_error($errors)) {
                    $error_message = $errors->get_error_message();
                }
            } elseif ($result === false || $result === null) {
                $error_message = 'Installation failed. The ZIP file may be invalid or corrupted.';
            }
            throw new Exception($error_message);
            
        } catch (Exception $e) {
            // Clean up temp file
            if ($temp_file && file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            WP_Update_Agent_Logger::log_action('install_theme_zip', 'error', array(
                'error' => $e->getMessage(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_theme_zip',
                'failed' => array('zip'),
                'logs' => sprintf('Failed to install theme from ZIP: %s', $e->getMessage()),
            );
        }
    }
    
    /**
     * Validate ZIP file for security
     *
     * @param string $file_path
     * @return true|WP_Error
     */
    private static function validate_zip_file($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'ZIP file not found');
        }
        
        // Check MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file_path);
        
        $allowed_mimes = array('application/zip', 'application/x-zip-compressed', 'application/octet-stream');
        if (!in_array($mime_type, $allowed_mimes)) {
            return new WP_Error('invalid_mime', 'Invalid file type. Only ZIP files are allowed.');
        }
        
        // Open ZIP and validate contents
        $zip = new ZipArchive();
        $result = $zip->open($file_path);
        
        if ($result !== true) {
            return new WP_Error('invalid_zip', 'Cannot open ZIP file');
        }
        
        // Check for path traversal attempts
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Check for path traversal
            if (strpos($filename, '..') !== false || strpos($filename, '\\') !== false) {
                $zip->close();
                return new WP_Error('path_traversal', 'Invalid file path detected in ZIP');
            }
            
            // Check for absolute paths
            if (preg_match('/^\/|^[a-zA-Z]:/', $filename)) {
                $zip->close();
                return new WP_Error('absolute_path', 'Absolute path detected in ZIP');
            }
        }
        
        $zip->close();
        return true;
    }
    
    /**
     * Activate a theme
     *
     * @param string $slug Theme slug
     * @return array
     */
    public static function activate_theme($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'activate_theme',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        // Check if theme exists
        $theme = wp_get_theme($slug);
        
        if (!$theme->exists()) {
            WP_Update_Agent_Logger::log_action('activate_theme', 'error', array(
                'slug' => $slug,
                'error' => 'Theme not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'activate_theme',
                'failed' => array($slug),
                'logs' => sprintf('Theme "%s" not found', $slug),
            );
        }
        
        // Check if already active
        $current_theme = get_stylesheet();
        if ($slug === $current_theme) {
            return array(
                'status' => 'success',
                'action' => 'activate_theme',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => sprintf('Theme "%s" is already active', $slug),
            );
        }
        
        // Check if theme has errors
        $errors = $theme->errors();
        if (is_wp_error($errors)) {
            WP_Update_Agent_Logger::log_action('activate_theme', 'error', array(
                'slug' => $slug,
                'error' => $errors->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'activate_theme',
                'failed' => array($slug),
                'logs' => sprintf('Cannot activate theme "%s": %s', $slug, $errors->get_error_message()),
            );
        }
        
        switch_theme($slug);
        
        WP_Update_Agent_Logger::log_action('activate_theme', 'success', array(
            'slug' => $slug,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'activate_theme',
            'updated' => array($slug),
            'failed' => array(),
            'logs' => sprintf('Successfully activated theme "%s"', $slug),
        );
    }
    
    /**
     * Delete a theme
     *
     * @param string $slug Theme slug
     * @return array
     */
    public static function delete_theme($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'delete_theme',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        // Check if theme exists
        $theme = wp_get_theme($slug);
        
        if (!$theme->exists()) {
            WP_Update_Agent_Logger::log_action('delete_theme', 'error', array(
                'slug' => $slug,
                'error' => 'Theme not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_theme',
                'failed' => array($slug),
                'logs' => sprintf('Theme "%s" not found', $slug),
            );
        }
        
        // Prevent deleting active theme
        $current_theme = get_stylesheet();
        $parent_theme = get_template();
        
        if ($slug === $current_theme) {
            WP_Update_Agent_Logger::log_action('delete_theme', 'error', array(
                'slug' => $slug,
                'error' => 'Cannot delete active theme',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_theme',
                'failed' => array($slug),
                'logs' => sprintf('Cannot delete theme "%s" because it is currently active', $slug),
            );
        }
        
        // Prevent deleting parent of active theme
        if ($slug === $parent_theme && $slug !== $current_theme) {
            WP_Update_Agent_Logger::log_action('delete_theme', 'error', array(
                'slug' => $slug,
                'error' => 'Cannot delete parent theme',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_theme',
                'failed' => array($slug),
                'logs' => sprintf('Cannot delete theme "%s" because it is the parent of the active theme', $slug),
            );
        }
        
        // Delete the theme
        $result = delete_theme($slug);
        
        if (is_wp_error($result)) {
            WP_Update_Agent_Logger::log_action('delete_theme', 'error', array(
                'slug' => $slug,
                'error' => $result->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_theme',
                'failed' => array($slug),
                'logs' => sprintf('Failed to delete theme "%s": %s', $slug, $result->get_error_message()),
            );
        }
        
        WP_Update_Agent_Logger::log_action('delete_theme', 'success', array(
            'slug' => $slug,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'delete_theme',
            'updated' => array($slug),
            'failed' => array(),
            'logs' => sprintf('Successfully deleted theme "%s"', $slug),
        );
    }
}
