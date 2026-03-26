<?php
/**
 * Plugin Name: WP Sentinel Agent plugin
 * Plugin URI: https://github.com/your-repo/wp-update-agent
 * Description: A secure REST API agent for managing WordPress updates, plugin installations, and SMTP testing.
 * Version: 1.2.8
 * Author: Jeffrey Pieksma
 * Author URI: https://webdesign-joure.nl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-update-agent
 * Requires at least: 5.9
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WP_UPDATE_AGENT_VERSION', '1.2.8');
define('WP_UPDATE_AGENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_UPDATE_AGENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_UPDATE_AGENT_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Maximum ZIP file size (10MB)
define('WP_UPDATE_AGENT_MAX_ZIP_SIZE', 10 * 1024 * 1024);

// Timestamp tolerance (5 minutes)
define('WP_UPDATE_AGENT_TIMESTAMP_TOLERANCE', 300);

// Lock timeout (10 minutes)
define('WP_UPDATE_AGENT_LOCK_TIMEOUT', 600);

/**
 * Main plugin class
 */
final class WP_Update_Agent {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-authenticator.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-locker.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-plugin-handler.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-theme-handler.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-core-handler.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-smtp-handler.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-auto-login.php';
        
        if (is_admin()) {
            require_once WP_UPDATE_AGENT_PLUGIN_DIR . 'includes/class-admin-settings.php';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize REST API
        add_action('rest_api_init', array($this, 'init_rest_api'));
        
        // Initialize admin settings
        if (is_admin()) {
            add_action('admin_menu', array('WP_Update_Agent_Admin_Settings', 'add_menu'));
            add_action('admin_init', array('WP_Update_Agent_Admin_Settings', 'register_settings'));
        }
        
        // Activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        $rest_api = new WP_Update_Agent_REST_API();
        $rest_api->register_routes();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Generate a random secret if not exists
        if (!get_option('wp_update_agent_secret')) {
            update_option('wp_update_agent_secret', wp_generate_password(64, true, true));
        }
        
        // Create log directory
        $log_dir = WP_UPDATE_AGENT_PLUGIN_DIR . 'logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Add .htaccess to protect logs
            file_put_contents($log_dir . '/.htaccess', 'Deny from all');
            // Add index.php
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('wp_update_agent_lock');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Get the shared secret
     */
    public static function get_secret() {
        return get_option('wp_update_agent_secret', '');
    }
}

/**
 * Initialize the plugin
 */
function wp_update_agent() {
    return WP_Update_Agent::instance();
}

// Initialize
wp_update_agent();
