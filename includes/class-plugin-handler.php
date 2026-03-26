<?php
/**
 * Plugin Handler class for plugin operations
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Plugin_Handler
 * 
 * Handles all plugin-related operations
 */
class WP_Update_Agent_Plugin_Handler {
    
    /**
     * Valid plugin slug pattern
     */
    const SLUG_PATTERN = '/^[a-z0-9\-]+$/';
    
    /**
     * Validate plugin slug
     *
     * @param string $slug
     * @return true|WP_Error
     */
    public static function validate_slug($slug) {
        if (empty($slug)) {
            return new WP_Error(
                'agent_invalid_slug',
                __('Plugin slug is required.', 'wp-update-agent'),
                array('status' => 400)
            );
        }
        
        if (!preg_match(self::SLUG_PATTERN, $slug)) {
            return new WP_Error(
                'agent_invalid_slug',
                __('Invalid plugin slug format. Only lowercase letters, numbers, and hyphens are allowed.', 'wp-update-agent'),
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Load required WordPress admin files
     */
    private static function load_admin_files() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        
        // Ensure we have filesystem access
        WP_Filesystem();
    }
    
    /**
     * Get list of all plugins with status
     *
     * @return array
     */
    public static function get_plugin_list() {
        self::load_admin_files();
        
        // Force check for updates
        wp_update_plugins();
        
        $all_plugins = get_plugins();
        $update_plugins = get_site_transient('update_plugins');
        $active_plugins = get_option('active_plugins', array());
        
        $plugins = array();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            // Extract slug from plugin file
            $slug = self::get_slug_from_file($plugin_file);
            
            // Check if update is available
            $update_available = false;
            $new_version = null;
            
            if (isset($update_plugins->response[$plugin_file])) {
                $update_available = true;
                $new_version = $update_plugins->response[$plugin_file]->new_version;
            }
            
            $plugins[] = array(
                'slug' => $slug,
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'active' => in_array($plugin_file, $active_plugins),
                'update_available' => $update_available,
                'new_version' => $new_version,
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'plugin_uri' => $plugin_data['PluginURI'],
            );
        }
        
        WP_Update_Agent_Logger::log_action('plugin_list', 'success', array(
            'total_plugins' => count($plugins),
        ));
        
        return array(
            'status' => 'success',
            'action' => 'plugin_list',
            'plugins' => $plugins,
            'logs' => sprintf('Found %d plugins', count($plugins)),
        );
    }
    
    /**
     * Get slug from plugin file path
     *
     * @param string $plugin_file
     * @return string
     */
    private static function get_slug_from_file($plugin_file) {
        if (strpos($plugin_file, '/') !== false) {
            return dirname($plugin_file);
        }
        return basename($plugin_file, '.php');
    }
    
    /**
     * Get plugin file from slug
     *
     * @param string $slug
     * @return string|false
     */
    private static function get_file_from_slug($slug) {
        self::load_admin_files();
        
        $all_plugins = get_plugins();
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (self::get_slug_from_file($plugin_file) === $slug) {
                return $plugin_file;
            }
        }
        
        return false;
    }
    
    /**
     * Update a single plugin
     *
     * @param string $slug Plugin slug
     * @return array
     */
    public static function update_plugin($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'update_plugin',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        $plugin_file = self::get_file_from_slug($slug);
        
        if (!$plugin_file) {
            WP_Update_Agent_Logger::log_action('update_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'update_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Plugin "%s" not found', $slug),
            );
        }
        
        // Check if update is available
        $update_plugins = get_site_transient('update_plugins');
        
        if (!isset($update_plugins->response[$plugin_file])) {
            WP_Update_Agent_Logger::log_action('update_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'No update available',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'update_plugin',
                'failed' => array($slug),
                'logs' => sprintf('No update available for plugin "%s"', $slug),
            );
        }
        
        // Store active state BEFORE update
        $was_active = is_plugin_active($plugin_file);
        
        // Perform the update
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        $result = $upgrader->upgrade($plugin_file);
        
        // Check if update was successful - upgrade() returns true on success
        if ($result === true) {
            // Clear update transient
            delete_site_transient('update_plugins');
            wp_update_plugins();
            
            // Reactivate plugin if it was active before update
            if ($was_active) {
                // Re-fetch plugin file in case it changed
                $new_plugin_file = self::get_file_from_slug($slug);
                if ($new_plugin_file && !is_plugin_active($new_plugin_file)) {
                    $activate_result = activate_plugin($new_plugin_file);
                    if (is_wp_error($activate_result)) {
                        WP_Update_Agent_Logger::log_action('update_plugin', 'warning', array(
                            'slug' => $slug,
                            'warning' => 'Plugin updated but failed to reactivate: ' . $activate_result->get_error_message(),
                        ));
                    }
                }
            }
            
            WP_Update_Agent_Logger::log_action('update_plugin', 'success', array(
                'slug' => $slug,
                'was_active' => $was_active,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'update_plugin',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => sprintf('Successfully updated plugin "%s"', $slug),
            );
        }
        
        // Get error message
        $error_message = 'Unknown error';
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } elseif ($result === false || $result === null) {
            $error_message = 'Update failed. The plugin may not exist or an error occurred.';
        }
        
        WP_Update_Agent_Logger::log_action('update_plugin', 'error', array(
            'slug' => $slug,
            'error' => $error_message,
        ));
        
        return array(
            'status' => 'error',
            'action' => 'update_plugin',
            'failed' => array($slug),
            'logs' => sprintf('Failed to update plugin "%s": %s', $slug, $error_message),
        );
    }
    
    /**
     * Update all plugins with available updates
     *
     * @return array
     */
    public static function update_all_plugins() {
        self::load_admin_files();
        
        // Force check for updates
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        $update_plugins = get_site_transient('update_plugins');
        
        if (empty($update_plugins->response)) {
            WP_Update_Agent_Logger::log_action('update_all_plugins', 'success', array(
                'message' => 'No updates available',
            ));
            
            return array(
                'status' => 'success',
                'action' => 'update_all_plugins',
                'updated' => array(),
                'failed' => array(),
                'logs' => 'No plugin updates available',
            );
        }
        
        $updated = array();
        $failed = array();
        $logs = array();
        
        // Store active state for ALL plugins before updating
        $active_plugins_before = array();
        foreach ($update_plugins->response as $plugin_file => $plugin_info) {
            $slug = self::get_slug_from_file($plugin_file);
            $active_plugins_before[$slug] = is_plugin_active($plugin_file);
        }
        
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        foreach ($update_plugins->response as $plugin_file => $plugin_info) {
            $slug = self::get_slug_from_file($plugin_file);
            $was_active = $active_plugins_before[$slug] ?? false;
            
            $result = $upgrader->upgrade($plugin_file);
            
            // Check if update was successful - upgrade() returns true on success
            if ($result === true) {
                // Reactivate plugin if it was active before update
                if ($was_active) {
                    $new_plugin_file = self::get_file_from_slug($slug);
                    if ($new_plugin_file && !is_plugin_active($new_plugin_file)) {
                        $activate_result = activate_plugin($new_plugin_file);
                        if (is_wp_error($activate_result)) {
                            $logs[] = sprintf('Updated but failed to reactivate: %s', $slug);
                        }
                    }
                }
                
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
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        $status = empty($failed) ? 'success' : (empty($updated) ? 'error' : 'partial');
        
        WP_Update_Agent_Logger::log_action('update_all_plugins', $status, array(
            'updated' => $updated,
            'failed' => $failed,
        ));
        
        return array(
            'status' => $status,
            'action' => 'update_all_plugins',
            'updated' => $updated,
            'failed' => $failed,
            'logs' => implode("\n", $logs),
        );
    }
    
    /**
     * Install a plugin from WordPress.org by slug
     *
     * @param string $slug Plugin slug
     * @param bool $activate Whether to activate after installation
     * @return array
     */
    public static function install_plugin_slug($slug, $activate = false) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'install_plugin_slug',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        // Check if plugin already exists
        $existing_file = self::get_file_from_slug($slug);
        if ($existing_file) {
            WP_Update_Agent_Logger::log_action('install_plugin_slug', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin already installed',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_plugin_slug',
                'failed' => array($slug),
                'logs' => sprintf('Plugin "%s" is already installed', $slug),
            );
        }
        
        // Get plugin info from WordPress.org
        $api = plugins_api('plugin_information', array(
            'slug' => $slug,
            'fields' => array(
                'short_description' => false,
                'sections' => false,
                'requires' => false,
                'rating' => false,
                'ratings' => false,
                'downloaded' => false,
                'last_updated' => false,
                'added' => false,
                'tags' => false,
                'compatibility' => false,
                'homepage' => false,
                'donate_link' => false,
            ),
        ));
        
        if (is_wp_error($api)) {
            WP_Update_Agent_Logger::log_action('install_plugin_slug', 'error', array(
                'slug' => $slug,
                'error' => $api->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_plugin_slug',
                'failed' => array($slug),
                'logs' => sprintf('Could not fetch plugin info for "%s": %s', $slug, $api->get_error_message()),
            );
        }
        
        // Install the plugin
        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        
        $result = $upgrader->install($api->download_link);
        
        // Check if installation was successful - install() returns true on success
        if ($result === true) {
            $logs = sprintf('Successfully installed plugin "%s"', $slug);
            
            // Activate if requested
            if ($activate) {
                $plugin_file = self::get_file_from_slug($slug);
                if ($plugin_file) {
                    $activate_result = activate_plugin($plugin_file);
                    if (is_wp_error($activate_result)) {
                        $logs .= sprintf(' | Activation failed: %s', $activate_result->get_error_message());
                    } else {
                        $logs .= ' | Plugin activated';
                    }
                }
            }
            
            WP_Update_Agent_Logger::log_action('install_plugin_slug', 'success', array(
                'slug' => $slug,
                'activated' => $activate,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'install_plugin_slug',
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
            $error_message = 'Installation failed. The plugin may already be installed.';
        }
        
        WP_Update_Agent_Logger::log_action('install_plugin_slug', 'error', array(
            'slug' => $slug,
            'error' => $error_message,
        ));
        
        return array(
            'status' => 'error',
            'action' => 'install_plugin_slug',
            'failed' => array($slug),
            'logs' => sprintf('Failed to install plugin "%s": %s', $slug, $error_message),
        );
    }
    
    /**
     * Install a plugin from uploaded ZIP file
     *
     * @param array $file_data File data from $_FILES or base64 encoded content
     * @param bool $activate Whether to activate after installation
     * @return array
     */
    public static function install_plugin_zip($file_data, $activate = false) {
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
            
            // Install the plugin with overwrite enabled for existing plugins
            $skin = new WP_Ajax_Upgrader_Skin();
            $upgrader = new Plugin_Upgrader($skin);
            
            // Use overwrite_package to allow updating existing plugins
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
                // Get installed plugin info
                $installed_plugin = $upgrader->plugin_info();
                $slug = $installed_plugin ? self::get_slug_from_file($installed_plugin) : 'unknown';
                
                $logs = sprintf('Successfully installed plugin from ZIP');
                
                // Activate if requested
                if ($activate && $installed_plugin) {
                    $activate_result = activate_plugin($installed_plugin);
                    if (is_wp_error($activate_result)) {
                        $logs .= sprintf(' | Activation failed: %s', $activate_result->get_error_message());
                    } else {
                        $logs .= ' | Plugin activated';
                    }
                }
                
                WP_Update_Agent_Logger::log_action('install_plugin_zip', 'success', array(
                    'slug' => $slug,
                    'activated' => $activate,
                ));
                
                return array(
                    'status' => 'success',
                    'action' => 'install_plugin_zip',
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
            
            WP_Update_Agent_Logger::log_action('install_plugin_zip', 'error', array(
                'error' => $e->getMessage(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'install_plugin_zip',
                'failed' => array('zip'),
                'logs' => sprintf('Failed to install plugin from ZIP: %s', $e->getMessage()),
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
     * Activate a plugin
     *
     * @param string $slug Plugin slug
     * @return array
     */
    public static function activate_plugin($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'activate_plugin',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        $plugin_file = self::get_file_from_slug($slug);
        
        if (!$plugin_file) {
            WP_Update_Agent_Logger::log_action('activate_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'activate_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Plugin "%s" not found', $slug),
            );
        }
        
        // Check if already active
        if (is_plugin_active($plugin_file)) {
            return array(
                'status' => 'success',
                'action' => 'activate_plugin',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => sprintf('Plugin "%s" is already active', $slug),
            );
        }
        
        $result = activate_plugin($plugin_file);
        
        if (is_wp_error($result)) {
            WP_Update_Agent_Logger::log_action('activate_plugin', 'error', array(
                'slug' => $slug,
                'error' => $result->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'activate_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Failed to activate plugin "%s": %s', $slug, $result->get_error_message()),
            );
        }
        
        WP_Update_Agent_Logger::log_action('activate_plugin', 'success', array(
            'slug' => $slug,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'activate_plugin',
            'updated' => array($slug),
            'failed' => array(),
            'logs' => sprintf('Successfully activated plugin "%s"', $slug),
        );
    }
    
    /**
     * Deactivate a plugin
     *
     * @param string $slug Plugin slug
     * @return array
     */
    public static function deactivate_plugin($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'deactivate_plugin',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        $plugin_file = self::get_file_from_slug($slug);
        
        if (!$plugin_file) {
            WP_Update_Agent_Logger::log_action('deactivate_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'deactivate_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Plugin "%s" not found', $slug),
            );
        }
        
        // Check if already inactive
        if (!is_plugin_active($plugin_file)) {
            return array(
                'status' => 'success',
                'action' => 'deactivate_plugin',
                'updated' => array($slug),
                'failed' => array(),
                'logs' => sprintf('Plugin "%s" is already inactive', $slug),
            );
        }
        
        // Prevent deactivating this plugin
        if ($plugin_file === WP_UPDATE_AGENT_PLUGIN_BASENAME) {
            return array(
                'status' => 'error',
                'action' => 'deactivate_plugin',
                'failed' => array($slug),
                'logs' => 'Cannot deactivate the WP Update Agent plugin',
            );
        }
        
        deactivate_plugins($plugin_file);
        
        WP_Update_Agent_Logger::log_action('deactivate_plugin', 'success', array(
            'slug' => $slug,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'deactivate_plugin',
            'updated' => array($slug),
            'failed' => array(),
            'logs' => sprintf('Successfully deactivated plugin "%s"', $slug),
        );
    }
    
    /**
     * Delete a plugin
     *
     * @param string $slug Plugin slug
     * @return array
     */
    public static function delete_plugin($slug) {
        $validation = self::validate_slug($slug);
        if (is_wp_error($validation)) {
            return array(
                'status' => 'error',
                'action' => 'delete_plugin',
                'failed' => array($slug),
                'logs' => $validation->get_error_message(),
            );
        }
        
        self::load_admin_files();
        
        $plugin_file = self::get_file_from_slug($slug);
        
        if (!$plugin_file) {
            WP_Update_Agent_Logger::log_action('delete_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin not found',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Plugin "%s" not found', $slug),
            );
        }
        
        // Prevent deleting this plugin
        if ($plugin_file === WP_UPDATE_AGENT_PLUGIN_BASENAME) {
            return array(
                'status' => 'error',
                'action' => 'delete_plugin',
                'failed' => array($slug),
                'logs' => 'Cannot delete the WP Update Agent plugin',
            );
        }
        
        // Check if plugin is active
        if (is_plugin_active($plugin_file)) {
            WP_Update_Agent_Logger::log_action('delete_plugin', 'error', array(
                'slug' => $slug,
                'error' => 'Plugin is still active',
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Cannot delete plugin "%s" because it is still active. Please deactivate it first.', $slug),
            );
        }
        
        // Delete the plugin
        $result = delete_plugins(array($plugin_file));
        
        if (is_wp_error($result)) {
            WP_Update_Agent_Logger::log_action('delete_plugin', 'error', array(
                'slug' => $slug,
                'error' => $result->get_error_message(),
            ));
            
            return array(
                'status' => 'error',
                'action' => 'delete_plugin',
                'failed' => array($slug),
                'logs' => sprintf('Failed to delete plugin "%s": %s', $slug, $result->get_error_message()),
            );
        }
        
        WP_Update_Agent_Logger::log_action('delete_plugin', 'success', array(
            'slug' => $slug,
        ));
        
        return array(
            'status' => 'success',
            'action' => 'delete_plugin',
            'deleted' => array($slug),
            'failed' => array(),
            'logs' => sprintf('Successfully deleted plugin "%s"', $slug),
        );
    }
}
