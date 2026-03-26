<?php
/**
 * Admin Settings class for plugin configuration
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Admin_Settings
 * 
 * Handles the admin settings page with modern UI
 */
class WP_Update_Agent_Admin_Settings {
    
    const OPTION_GROUP = 'wp_update_agent_settings';
    
    /**
     * Add admin menu
     */
    public static function add_menu() {
        add_menu_page(
            __('WP Update Agent', 'wp-update-agent'),
            __('Update Agent', 'wp-update-agent'),
            'manage_options',
            'wp-update-agent',
            array(__CLASS__, 'render_settings_page'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTY3IiBoZWlnaHQ9IjE2NCIgdmlld0JveD0iMCAwIDE2NyAxNjQiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNMTA2LjIzNyAxNTUuMTk1QzkwLjM5NDcgMTY0LjU4IDg5LjAxNSAxNjQuNzY1IDg4Ljk0MSAxNjMuMzU5Qzg4LjYxNiAxNTcuMTkzIDg4LjkyNDMgNzUuNjA3NiA4OC45NTI3IDY3Ljk3NjhDODguOTU1NSA2Ny4yOTE4IDg4LjQ0NTEgNjQuMzgyMiA5MC4yMjA1IDY0LjE1MzhDOTMuMjgyNiA2My43NjA0IDExOS45MjEgNjIuOTI4NCAxNTEuNTQyIDQyLjA1NzNDMTUyLjY0MiA0MS4zMzExIDE1OS45MzYgMzYuNTE3MSAxNjQuNjI2IDMyLjIxOTRDMTY0Ljc2NyAzMi4wOTEgMTY2Ljg5NiAzMC4xNDAzIDE2Ni45MzkgMzEuMzU3M0MxNjcuMDUyIDM0LjQ4NDIgMTY3LjM5NyA3Ny40NTM4IDE2My41MTYgODEuOTM5MkMxNjEuNTg5IDg0LjE2NjMgMTQ5LjkzNyA5My4zNjAzIDE0MS43ODkgOTcuODczOEMxMTcuMzk1IDExMS4zODggMTAzLjk0OSAxMTAuNzk4IDEwMy42NzUgMTEzLjE2NEMxMDMuNDQ0IDExNS4xNTkgMTAzLjcwNyAxMTUuMTUzIDEwMy41OTggMTM4LjIzNEMxMDMuNTkxIDEzOS43MjMgMTAzLjA4NSAxNDAuNzg1IDEwNC42MTMgMTQwLjE0M0MxMDYuMTQ1IDEzOS41IDEyMS43OTkgMTI3LjcyMSAxMzEuNjg1IDExNS4wMDZDMTMyLjMxNiAxMTQuMTk1IDEzNC4zOCAxMTEuMzk1IDEzNC42MTMgMTExLjA4QzEzNS44MjQgMTA5LjQzNiAxNDcuODcgMTA1LjAzNSAxNTcuMTM3IDk3LjgyODZDMTU5LjU3NiA5NS45MzE3IDE1OC4wNjEgOTguOTYzOCAxNTguMDA3IDk5LjA3MDdDMTU1LjIwNCAxMDQuNjggMTQzLjU2OCAxMzIuNTI5IDEwNi4yMzcgMTU1LjE5NVpNMTUxLjYwNCA2Ni40ODc0QzE1MS45OTYgNjIuMzI5NyAxNTIuOTczIDU2LjgwMSAxNTAuNzk3IDU4LjM2ODhDMTQ5Ljg1NyA1OS4wNDYyIDE0NC4wMzMgNjIuMjA5MyAxNDAuOTEyIDYzLjYxMDRDMTIwLjI3NSA3Mi44NzQ2IDExNS40NjkgNzMuMDYzMyAxMDQuNjQgNzUuNDgwMkMxMDMuNTE5IDc1LjczMDYgMTAzLjYyNSA3Ni4xNzY3IDEwMy42MTcgOTcuNTgxMkMxMDMuNjE2IDk5LjMwMzUgMTA0LjU0NCA5OC42MzMxIDEwNi4zMzggOTguMTQ2M0MxMjguNTg4IDkyLjExMDggMTQzLjgxMyA4MC40MDIxIDE0Ni42MDggNzguMjUyOEMxNTIuMjA1IDczLjk0ODYgMTUwLjcxOCA3My4wNTk4IDE1MS42MDQgNjYuNDg3NFoiIGZpbGw9IiM3Q0QwQTUiLz4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik02My4wMTkyIDEwMy40MzVDNjMuNTU0NiAxMDMuMTU1IDYzLjcwMzIgMTAzLjIxOSA2My42NjMxIDEwMi42NzhDNjMuNDgyMiAxMDAuMjY1IDYzLjU1NTcgODYuMjQxNCA2My41Njc0IDY2LjQ3MzhDNjMuNTY5IDYzLjcxMDggNjIuNzU5MyA2Mi4yMTkzIDY1Ljc0MTggNjIuNzE5MUM3Mi44OTQ2IDYzLjkxNzUgNzYuNjYwMiA2My45NTQxIDc3LjQ4NzggNjQuMjg4NEM3OC4wNzY2IDY0LjUyNjIgNzguMTQwMSA2NC43NzIxIDc4LjEzNTEgMTA0LjYxMkM3OC4xMzEyIDEzNS41MjMgNzkuMTYzNiAxMzguMjA0IDc2LjgwNTUgMTM2Ljc1NUM2Ny41NjE3IDEzMS4wNzIgNjUuMjY2NSAxMjguNDI3IDYzLjI0NzQgMTI2LjM3QzQ2LjAxNzEgMTA4LjgxOSA0NC4yMDc4IDk1LjM0NjYgNDIuNDg0MSA5OC42NzQ3QzM4Ljg1MDQgMTA1LjY5MiAzOS4wMjA3IDEwNS43MjcgMzQuOTQ4NCAxMTIuNTc5QzMzLjQ4MDggMTE1LjA0OSA0OS4xNzY2IDEzMC4xNzEgNTUuNDQ5IDEzNC45NTFDNzMuMTc1NyAxNDguNDYgNzguMTM1NiAxNDYuOTI4IDc4LjE1MDcgMTUwLjc4Qzc4LjIwMDIgMTYzLjIyNCA3OC40IDE2NC41NTEgNzYuODEyNyAxNjMuODQ3QzY5Ljc4OTEgMTYwLjczIDU5LjY5MjcgMTU0LjQzMyA1OC4yNTg0IDE1My41MzlDMjIuMjc0NyAxMzEuMDk5IDEuMDQyOCA5NS45ODg0IDAuMjI1MjIzIDU4Ljk0MTlDMC4xNzYyNDcgNTYuNzM2MyAtMC4xOTYwOCAzOS44Njk4IDAuMTM3Mjk0IDMxLjM2OTNDMC4yMTYzMjQgMjkuMzQ1IDMuMjUzOTggMzMuNzIwOSAxMi41OTk2IDQwLjEyNDhDMTYuNjU3OSA0Mi45MDU0IDEzLjMzMTQgNDQuMTIyMyAxNS41MDAzIDY2LjQ4MjRDMTYuOTQ3NCA4MS40MDUzIDI0LjczMjkgOTguODg5NSAyNS42NTI5IDk5LjAwMjlDMjYuODMyMyA5OS4xNDg1IDMyLjEyIDg3LjUyNDEgMzQuNTc4MyA3Ny41MjQ2QzM1LjU0NjIgNzMuNTg3OCAzMS45MDU4IDY3LjU1MDIgMzIuMDczOCA1Mi44OTc4QzMyLjA4NSA1MS45MTY3IDMyLjQ5MzUgNTEuODMwOSAzMy40NjAyIDUyLjMyMDdDMzkuNTczOSA1NS40MTc1IDM5LjczNDggNTUuMDUxMiA0Ni4xOTA4IDU3LjQ3MkM0Ny4yODQ0IDU3Ljg4MiA0Ny4zMjUgNjIuNTQ3IDQ3LjU5NDkgNjQuNDMzNEM1MC4zNjQ5IDgzLjc2ODQgNjEuODA5MiAxMDEuOTM2IDYzLjAxOTIgMTAzLjQzNVoiIGZpbGw9IiMxRTYzODUiLz4KPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05Ny45Njk0IDMzLjAyMTNDMTEwLjQ4IDMyLjE1OTIgMTA4LjM0OSAyMC4zNzcyIDEwOS45NDcgMjQuOTAxN0MxMTEuNDg3IDI5LjI1ODEgMTA5Ljk3MSAzNS40ODg4IDEwOS43NTMgMzYuMzg2MUMxMDUuMzQ2IDU0LjQ5OTcgNzQuNjQ0OSA2My4wOTUgNjAuMzg3NyA0Mi43MzkzQzU0LjE2NTUgMzMuODU2NCA1Ni41MDQ3IDIyLjMyNTMgNjEuMjU5MyAxNi44MDQ3QzYxLjI5NzEgMTYuNzYwNiA2My45MTg1IDE0LjY1NjQgNjAuNjk0NCAxNS4zNTg0QzM0LjUwOTggMjEuMDYxMiAyOC43MzM5IDI1LjI4NzcgMTcuNzcxIDMwLjc0QzE2Ljc4MzEgMzEuMjMxMyAxNi4zOTQxIDMxLjE0NCA2Ljg5ODE5IDIyLjY0M0M1LjM0MDQxIDIxLjI0NzkgOS41MzU2OSAxOS42NTE2IDEyLjY3MyAxOC4wNjQ4Qzc2LjEwNTggLTE0LjAyMjcgMTI1LjY1MyAzLjE4NjA2IDE1Ny4zNzMgMTkuNTMxN0MxNTcuNjY3IDE5LjY4MzIgMTYwLjc2NiAyMS4yOCAxNjAuNzc2IDIxLjgxMjVDMTYwLjc4NyAyMi40MjEyIDE1MC45MyAzMC45MjY3IDE0OS43NDMgMzAuNzgwN0MxNDcuNjc0IDMwLjUyNjggMTMwLjA5MiAxOC4wNTk4IDk1Ljc0NzYgMTMuNzUzNkM4OC41OTkzIDEyLjg1NzMgODEuMjAzMyAyNi44NDE4IDkzLjU4OTMgMzIuMjA1OUM5NS4wNDkyIDMyLjgzNzcgOTcuNjEyNiAzMi45OTg4IDk3Ljk2OTQgMzMuMDIxM1oiIGZpbGw9IiMxRTYzODYiLz4KPC9zdmc+Cg==',
            80
        );
    }
    
    /**
     * Register settings and AJAX handlers
     */
    public static function register_settings() {
        register_setting(self::OPTION_GROUP, 'wp_update_agent_secret', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
        
        // AJAX handlers
        add_action('wp_ajax_wp_update_agent_test', array(__CLASS__, 'handle_ajax_test'));
        add_action('wp_ajax_wp_update_agent_get_docs', array(__CLASS__, 'handle_ajax_get_docs'));
    }
    
    /**
     * Handle AJAX test requests
     */
    public static function handle_ajax_test() {
        check_ajax_referer('wp_update_agent_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $action = isset($_POST['test_action']) ? sanitize_text_field($_POST['test_action']) : '';
        $params = isset($_POST['params']) ? json_decode(stripslashes($_POST['params']), true) : array();
        
        if (empty($action)) {
            wp_send_json_error(array('message' => 'No action specified'));
        }
        
        // Load required files
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';
        
        WP_Filesystem();
        
        $result = self::execute_test_action($action, $params);
        wp_send_json_success($result);
    }
    
    /**
     * Execute a test action
     */
    private static function execute_test_action($action, $params) {
        switch ($action) {
            case 'plugin_list':
                return WP_Update_Agent_Plugin_Handler::get_plugin_list();
            case 'update_plugin':
                return WP_Update_Agent_Plugin_Handler::update_plugin($params['slug'] ?? '');
            case 'update_all_plugins':
                return WP_Update_Agent_Plugin_Handler::update_all_plugins();
            case 'install_plugin_slug':
                return WP_Update_Agent_Plugin_Handler::install_plugin_slug($params['slug'] ?? '', $params['activate'] ?? false);
            case 'activate_plugin':
                return WP_Update_Agent_Plugin_Handler::activate_plugin($params['slug'] ?? '');
            case 'deactivate_plugin':
                return WP_Update_Agent_Plugin_Handler::deactivate_plugin($params['slug'] ?? '');
            case 'delete_plugin':
                return WP_Update_Agent_Plugin_Handler::delete_plugin($params['slug'] ?? '');
            case 'theme_list':
                return WP_Update_Agent_Theme_Handler::get_theme_list();
            case 'update_theme':
                return WP_Update_Agent_Theme_Handler::update_theme($params['slug'] ?? '');
            case 'update_all_themes':
                return WP_Update_Agent_Theme_Handler::update_all_themes();
            case 'install_theme_slug':
                return WP_Update_Agent_Theme_Handler::install_theme_slug($params['slug'] ?? '', $params['activate'] ?? false);
            case 'activate_theme':
                return WP_Update_Agent_Theme_Handler::activate_theme($params['slug'] ?? '');
            case 'delete_theme':
                return WP_Update_Agent_Theme_Handler::delete_theme($params['slug'] ?? '');
            case 'core_check':
                return WP_Update_Agent_Core_Handler::core_check();
            case 'core_update':
                return WP_Update_Agent_Core_Handler::core_update($params['version'] ?? null);
            case 'language_update':
                return WP_Update_Agent_Core_Handler::language_update();
            case 'smtp_test':
                return WP_Update_Agent_SMTP_Handler::test_smtp($params['to'] ?? null);
            case 'smtp_info':
                return WP_Update_Agent_SMTP_Handler::get_smtp_info();
            case 'system_status':
                return WP_Update_Agent_Core_Handler::system_status();
            default:
                return array('status' => 'error', 'logs' => 'Unknown action: ' . $action);
        }
    }
    
    /**
     * Get API documentation data
     */
    public static function get_api_documentation() {
        return array(
            'version' => WP_UPDATE_AGENT_VERSION,
            'base_url' => rest_url('agent/v1'),
            'authentication' => array(
                'type' => 'HMAC-SHA256',
                'headers' => array(
                    'X-Agent-Timestamp' => 'Unix timestamp (seconds)',
                    'X-Agent-Signature' => 'HMAC-SHA256 signature',
                    'Content-Type' => 'application/json',
                ),
                'signature_format' => 'HMAC-SHA256(timestamp + "." + body, secret)',
                'timestamp_tolerance' => '300 seconds (5 minutes)',
            ),
            'endpoints' => array(
                array(
                    'path' => '/execute',
                    'method' => 'POST',
                    'description' => 'Execute agent actions',
                    'url' => rest_url('agent/v1/execute'),
                ),
                array(
                    'path' => '/test-smtp',
                    'method' => 'POST',
                    'description' => 'Test SMTP configuration',
                    'url' => rest_url('agent/v1/test-smtp'),
                ),
                array(
                    'path' => '/status',
                    'method' => 'POST',
                    'description' => 'Get agent status',
                    'url' => rest_url('agent/v1/status'),
                ),
            ),
            'actions' => array(
                // Plugin actions
                array(
                    'name' => 'plugin_list',
                    'category' => 'plugins',
                    'description' => 'List all installed plugins with status',
                    'parameters' => array(),
                    'example_request' => array('action' => 'plugin_list'),
                    'example_response' => array(
                        'status' => 'success',
                        'action' => 'plugin_list',
                        'plugins' => array(
                            array(
                                'slug' => 'akismet',
                                'name' => 'Akismet Anti-spam',
                                'version' => '5.0',
                                'active' => true,
                                'update_available' => false,
                            ),
                        ),
                    ),
                ),
                array(
                    'name' => 'update_plugin',
                    'category' => 'plugins',
                    'description' => 'Update a specific plugin',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Plugin slug'),
                    ),
                    'example_request' => array('action' => 'update_plugin', 'slug' => 'akismet'),
                ),
                array(
                    'name' => 'update_all_plugins',
                    'category' => 'plugins',
                    'description' => 'Update all plugins with available updates',
                    'parameters' => array(),
                    'example_request' => array('action' => 'update_all_plugins'),
                ),
                array(
                    'name' => 'install_plugin_slug',
                    'category' => 'plugins',
                    'description' => 'Install a plugin from WordPress.org',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Plugin slug from WordPress.org'),
                        array('name' => 'activate', 'type' => 'boolean', 'required' => false, 'description' => 'Activate after installation'),
                    ),
                    'example_request' => array('action' => 'install_plugin_slug', 'slug' => 'contact-form-7', 'activate' => true),
                ),
                array(
                    'name' => 'install_plugin_zip',
                    'category' => 'plugins',
                    'description' => 'Install a plugin from ZIP file',
                    'parameters' => array(
                        array('name' => 'file', 'type' => 'object', 'required' => true, 'description' => 'File object with filename and base64 content'),
                        array('name' => 'activate', 'type' => 'boolean', 'required' => false, 'description' => 'Activate after installation'),
                    ),
                    'example_request' => array(
                        'action' => 'install_plugin_zip',
                        'file' => array('filename' => 'my-plugin.zip', 'content' => 'BASE64_ENCODED_CONTENT'),
                        'activate' => true,
                    ),
                ),
                array(
                    'name' => 'activate_plugin',
                    'category' => 'plugins',
                    'description' => 'Activate an installed plugin',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Plugin slug'),
                    ),
                    'example_request' => array('action' => 'activate_plugin', 'slug' => 'akismet'),
                ),
                array(
                    'name' => 'deactivate_plugin',
                    'category' => 'plugins',
                    'description' => 'Deactivate an active plugin',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Plugin slug'),
                    ),
                    'example_request' => array('action' => 'deactivate_plugin', 'slug' => 'akismet'),
                ),
                array(
                    'name' => 'delete_plugin',
                    'category' => 'plugins',
                    'description' => 'Delete an installed plugin (must be deactivated first)',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Plugin slug'),
                    ),
                    'example_request' => array('action' => 'delete_plugin', 'slug' => 'hello'),
                ),
                // Theme actions
                array(
                    'name' => 'theme_list',
                    'category' => 'themes',
                    'description' => 'List all installed themes with status',
                    'parameters' => array(),
                    'example_request' => array('action' => 'theme_list'),
                    'example_response' => array(
                        'status' => 'success',
                        'action' => 'theme_list',
                        'themes' => array(
                            array(
                                'slug' => 'twentytwentyfour',
                                'name' => 'Twenty Twenty-Four',
                                'version' => '1.0',
                                'active' => true,
                                'update_available' => false,
                            ),
                        ),
                        'current_theme' => 'twentytwentyfour',
                    ),
                ),
                array(
                    'name' => 'update_theme',
                    'category' => 'themes',
                    'description' => 'Update a specific theme',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Theme slug'),
                    ),
                    'example_request' => array('action' => 'update_theme', 'slug' => 'twentytwentyfour'),
                ),
                array(
                    'name' => 'update_all_themes',
                    'category' => 'themes',
                    'description' => 'Update all themes with available updates',
                    'parameters' => array(),
                    'example_request' => array('action' => 'update_all_themes'),
                ),
                array(
                    'name' => 'install_theme_slug',
                    'category' => 'themes',
                    'description' => 'Install a theme from WordPress.org',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Theme slug from WordPress.org'),
                        array('name' => 'activate', 'type' => 'boolean', 'required' => false, 'description' => 'Activate after installation'),
                    ),
                    'example_request' => array('action' => 'install_theme_slug', 'slug' => 'flavflavor', 'activate' => true),
                ),
                array(
                    'name' => 'install_theme_zip',
                    'category' => 'themes',
                    'description' => 'Install a theme from ZIP file',
                    'parameters' => array(
                        array('name' => 'file', 'type' => 'object', 'required' => true, 'description' => 'File object with filename and base64 content'),
                        array('name' => 'activate', 'type' => 'boolean', 'required' => false, 'description' => 'Activate after installation'),
                    ),
                    'example_request' => array(
                        'action' => 'install_theme_zip',
                        'file' => array('filename' => 'my-theme.zip', 'content' => 'BASE64_ENCODED_CONTENT'),
                        'activate' => true,
                    ),
                ),
                array(
                    'name' => 'activate_theme',
                    'category' => 'themes',
                    'description' => 'Activate an installed theme',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Theme slug'),
                    ),
                    'example_request' => array('action' => 'activate_theme', 'slug' => 'twentytwentyfour'),
                ),
                array(
                    'name' => 'delete_theme',
                    'category' => 'themes',
                    'description' => 'Delete an installed theme (cannot delete active theme)',
                    'parameters' => array(
                        array('name' => 'slug', 'type' => 'string', 'required' => true, 'description' => 'Theme slug'),
                    ),
                    'example_request' => array('action' => 'delete_theme', 'slug' => 'twentytwentythree'),
                ),
                // Core actions
                array(
                    'name' => 'core_check',
                    'category' => 'core',
                    'description' => 'Check for WordPress core updates',
                    'parameters' => array(),
                    'example_request' => array('action' => 'core_check'),
                    'example_response' => array(
                        'status' => 'success',
                        'action' => 'core_check',
                        'current_version' => '6.4.2',
                        'update_available' => true,
                        'new_version' => '6.4.3',
                    ),
                ),
                array(
                    'name' => 'core_update',
                    'category' => 'core',
                    'description' => 'Update WordPress core',
                    'parameters' => array(
                        array('name' => 'version', 'type' => 'string', 'required' => false, 'description' => 'Specific version to update to'),
                    ),
                    'example_request' => array('action' => 'core_update'),
                ),
                array(
                    'name' => 'language_update',
                    'category' => 'core',
                    'description' => 'Update WordPress language files',
                    'parameters' => array(),
                    'example_request' => array('action' => 'language_update'),
                ),
                array(
                    'name' => 'system_status',
                    'category' => 'core',
                    'description' => 'Get comprehensive system status',
                    'parameters' => array(),
                    'example_request' => array('action' => 'system_status'),
                ),
                // SMTP actions
                array(
                    'name' => 'smtp_test',
                    'category' => 'smtp',
                    'description' => 'Test SMTP configuration by sending a test email',
                    'parameters' => array(
                        array('name' => 'to', 'type' => 'string', 'required' => false, 'description' => 'Email address (defaults to admin_email)'),
                    ),
                    'example_request' => array('action' => 'smtp_test', 'to' => 'test@example.com'),
                ),
                array(
                    'name' => 'smtp_info',
                    'category' => 'smtp',
                    'description' => 'Get SMTP configuration information',
                    'parameters' => array(),
                    'example_request' => array('action' => 'smtp_info'),
                ),
            ),
            'response_format' => array(
                'status' => 'success | error | partial',
                'action' => 'The action that was executed',
                'updated' => 'Array of successfully updated items (optional)',
                'failed' => 'Array of failed items (optional)',
                'logs' => 'Human readable log message',
                'message' => 'Additional message (optional)',
                'data' => 'Additional data (optional)',
            ),
            'error_codes' => array(
                array('code' => 'agent_missing_signature', 'status' => 401, 'description' => 'X-Agent-Signature header missing'),
                array('code' => 'agent_missing_timestamp', 'status' => 401, 'description' => 'X-Agent-Timestamp header missing'),
                array('code' => 'agent_invalid_signature', 'status' => 401, 'description' => 'Invalid HMAC signature'),
                array('code' => 'agent_timestamp_expired', 'status' => 401, 'description' => 'Timestamp is too old (>5 min)'),
                array('code' => 'agent_locked', 'status' => 423, 'description' => 'Another operation is in progress'),
                array('code' => 'agent_invalid_slug', 'status' => 400, 'description' => 'Invalid plugin slug format'),
            ),
            'code_examples' => array(
                'typescript' => self::get_typescript_example(),
                'php' => self::get_php_example(),
                'curl' => self::get_curl_example(),
            ),
        );
    }
    
    private static function get_typescript_example() {
        return <<<'CODE'
import crypto from 'crypto';

interface AgentConfig {
    siteUrl: string;
    secret: string;
}

export class WordPressAgent {
    private siteUrl: string;
    private secret: string;
    
    constructor(config: AgentConfig) {
        this.siteUrl = config.siteUrl;
        this.secret = config.secret;
    }
    
    async execute<T>(action: string, params: Record<string, any> = {}): Promise<T> {
        const body = JSON.stringify({ action, ...params });
        const timestamp = Math.floor(Date.now() / 1000).toString();
        const payload = timestamp + '.' + body;
        const signature = crypto
            .createHmac('sha256', this.secret)
            .update(payload)
            .digest('hex');
        
        const response = await fetch(`${this.siteUrl}/wp-json/agent/v1/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Agent-Timestamp': timestamp,
                'X-Agent-Signature': signature,
            },
            body,
        });
        
        return response.json();
    }
    
    // Convenience methods
    getPlugins = () => this.execute('plugin_list');
    updatePlugin = (slug: string) => this.execute('update_plugin', { slug });
    updateAllPlugins = () => this.execute('update_all_plugins');
    installPlugin = (slug: string, activate = false) => 
        this.execute('install_plugin_slug', { slug, activate });
    checkCoreUpdate = () => this.execute('core_check');
    updateCore = () => this.execute('core_update');
    testSmtp = (to?: string) => this.execute('smtp_test', { to });
    getSystemStatus = () => this.execute('system_status');
}

// Usage
const agent = new WordPressAgent({
    siteUrl: process.env.WP_SITE_URL!,
    secret: process.env.WP_AGENT_SECRET!,
});

const plugins = await agent.getPlugins();
CODE;
    }
    
    private static function get_php_example() {
        return <<<'CODE'
<?php
function call_wp_agent($site_url, $secret, $action, $params = []) {
    $timestamp = time();
    $body = json_encode(array_merge(['action' => $action], $params));
    $payload = $timestamp . '.' . $body;
    $signature = hash_hmac('sha256', $payload, $secret);
    
    $response = wp_remote_post($site_url . '/wp-json/agent/v1/execute', [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Agent-Timestamp' => $timestamp,
            'X-Agent-Signature' => $signature,
        ],
        'body' => $body,
        'timeout' => 120,
    ]);
    
    return json_decode(wp_remote_retrieve_body($response), true);
}

// Usage
$plugins = call_wp_agent('https://mysite.com', 'secret', 'plugin_list');
CODE;
    }
    
    private static function get_curl_example() {
        return <<<'CODE'
# Generate signature
TIMESTAMP=$(date +%s)
SECRET="your-secret-key"
BODY='{"action":"plugin_list"}'
SIGNATURE=$(echo -n "$TIMESTAMP.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

# Make request
curl -X POST "https://yoursite.com/wp-json/agent/v1/execute" \
  -H "Content-Type: application/json" \
  -H "X-Agent-Timestamp: $TIMESTAMP" \
  -H "X-Agent-Signature: $SIGNATURE" \
  -d "$BODY"
CODE;
    }
    
    /**
     * AJAX handler for getting docs as JSON
     */
    public static function handle_ajax_get_docs() {
        check_ajax_referer('wp_update_agent_docs', 'nonce');
        wp_send_json_success(self::get_api_documentation());
    }
    
    /**
     * Render the settings page
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Initialize notices array
        $notices = array();
        
        // Handle form submissions
        if (isset($_POST['force_unlock']) && check_admin_referer('wp_update_agent_force_unlock')) {
            WP_Update_Agent_Locker::force_release();
            $notices[] = array('type' => 'success', 'message' => __('Lock has been released.', 'wp-update-agent'));
        }
        
        if (isset($_POST['clear_logs']) && check_admin_referer('wp_update_agent_clear_logs')) {
            WP_Update_Agent_Logger::clear_transient_logs();
            $notices[] = array('type' => 'success', 'message' => __('Logs have been cleared.', 'wp-update-agent'));
        }
        
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
        $tabs = array(
            'dashboard' => array('label' => __('Dashboard', 'wp-update-agent'), 'icon' => 'dashicons-dashboard'),
            'settings' => array('label' => __('Settings', 'wp-update-agent'), 'icon' => 'dashicons-admin-settings'),
            'test' => array('label' => __('API Tester', 'wp-update-agent'), 'icon' => 'dashicons-code-standards'),
            'logs' => array('label' => __('Logs', 'wp-update-agent'), 'icon' => 'dashicons-list-view'),
        );
        
        ?>
        <style>
            /* Hide default WordPress notices on this page */
            .wp-update-agent-page .notice,
            .wp-update-agent-page .updated,
            .wp-update-agent-page .error,
            .wp-update-agent-page .update-nag {
                display: none !important;
            }
            
            .wua-wrap { max-width: 1200px; margin: 20px auto; }
            .wua-header { background: linear-gradient(135deg, #0c121c 0%, #111827 50%, #0c121c 100%); color: white; padding: 30px; border-radius: 12px 12px 0 0; margin: -1px -1px 0 -1px; position: relative; overflow: hidden; }
            .wua-header::before { content: ''; position: absolute; top: -4rem; left: -4rem; width: 16rem; height: 16rem; background: radial-gradient(circle, rgba(16,185,129,0.12) 0%, transparent 70%); pointer-events: none; }
            .wua-header h1 { margin: 0 0 5px 0; font-size: 28px; font-weight: 600; color: #fafafa; display: flex; align-items: center; gap: 12px; }
            .wua-header h1 img { width: 32px; height: 32px; }
            .wua-header h1 .dashicons { font-size: 28px; width: 28px; height: 28px; }
            .wua-header p { margin: 0; opacity: 0.9; }
            .wua-tabs { display: flex; background: #fff; border-bottom: 1px solid #e5e5e5; padding: 0 20px; }
            .wua-tab { padding: 15px 20px; text-decoration: none; color: #737373; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
            .wua-tab:hover { color: #059669; background: #fafafa; }
            .wua-tab.active { color: #059669; border-bottom-color: #059669; }
            .wua-tab .dashicons { font-size: 18px; width: 18px; height: 18px; }
            .wua-content { background: #fff; padding: 30px; border-radius: 0 0 12px 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .wua-card { background: #fafafa; border-radius: 8px; padding: 20px; margin-bottom: 20px; border: 1px solid #e5e5e5; }
            .wua-card h3 { margin: 0 0 15px 0; color: #18181b; display: flex; align-items: center; gap: 10px; }
            .wua-card h3 .dashicons { color: #18181b; }
            .wua-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
            .wua-stat { background: white; border-radius: 8px; padding: 20px; text-align: center; border: 1px solid #e5e5e5; }
            .wua-stat-value { font-size: 32px; font-weight: 700; color: #059669; }
            .wua-stat-label { color: #737373; font-size: 14px; margin-top: 5px; }
            .wua-endpoint { background: white; border-radius: 6px; padding: 15px; margin-bottom: 10px; border: 1px solid #e5e5e5; }
            .wua-endpoint code { background: #f5f5f5; padding: 4px 8px; border-radius: 4px; font-size: 13px; }
            .wua-method { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-right: 10px; }
            .wua-method-post { background: #d4edda; color: #155724; }
            .wua-secret-field { font-family: monospace; background: #18181b; color: #d4d4d4; border: none; padding: 12px 15px; border-radius: 6px; width: 100%; max-width: 500px; }
            .wua-btn { padding: 10px 20px; border-radius: 6px; border: none; cursor: pointer; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; line-height: 1.4; }
            .wua-btn-primary { background: #059669 !important; color: #fff !important; border: none !important; box-shadow: none !important; text-shadow: none !important; }
            .wua-btn-primary:hover, .wua-btn-primary:focus { background: #047857 !important; color: #fff !important; border: none !important; box-shadow: none !important; }
            .wua-btn-secondary { background: #f5f5f5; color: #18181b; }
            .wua-btn-secondary:hover { background: #e5e5e5; }
            .wua-btn-danger { background: #dc3545; color: white; }
            .wua-btn-danger:hover { background: #c82333; }
            .wua-status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
            .wua-status-green { background: #10b981; }
            .wua-status-red { background: #dc3545; }
            .wua-status-yellow { background: #ffc107; }
            .wua-test-console { background: #fafafa; border-radius: 8px; padding: 25px; border: 1px solid #e5e5e5; }
            .wua-test-select { width: 100%; padding: 14px 16px; border-radius: 8px; border: 2px solid #e5e5e5; background: #fff; color: #18181b; margin-bottom: 15px; font-size: 14px; font-weight: 500; cursor: pointer; transition: border-color 0.2s; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2318181b' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; }
            .wua-test-select:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
            .wua-test-select optgroup { font-weight: 600; color: #059669; }
            .wua-test-select option { padding: 10px; font-weight: 400; color: #18181b; }
            .wua-test-input { width: 100%; padding: 14px 16px; border-radius: 8px; border: 2px solid #e5e5e5; background: #fff; color: #18181b; margin-bottom: 15px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; }
            .wua-test-input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15); }
            .wua-test-input::placeholder { color: #a1a1aa; }
            .wua-test-checkbox { display: flex; align-items: center; gap: 10px; color: #18181b; margin-bottom: 15px; font-size: 14px; }
            .wua-test-checkbox input[type="checkbox"] { width: 18px; height: 18px; accent-color: #059669; }
            .wua-test-output { background: #18181b; border-radius: 8px; padding: 20px; color: #e0e0e0; font-family: 'Monaco', 'Menlo', monospace; font-size: 13px; max-height: 400px; overflow: auto; white-space: pre-wrap; line-height: 1.6; }
            .wua-logs-table { width: 100%; border-collapse: collapse; }
            .wua-logs-table th, .wua-logs-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e5e5; }
            .wua-logs-table th { background: #fafafa; font-weight: 600; color: #18181b; }
            .wua-log-level { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
            .wua-log-info { background: #d1ecf1; color: #0c5460; }
            .wua-log-error { background: #f8d7da; color: #721c24; }
            .wua-log-warning { background: #fff3cd; color: #856404; }
            .wua-docs-section { margin-bottom: 30px; }
            .wua-docs-section h3 { border-bottom: 2px solid #059669; padding-bottom: 10px; margin-bottom: 20px; }
            .wua-code-block { background: #18181b; color: #d4d4d4; padding: 20px; border-radius: 8px; overflow-x: auto; font-family: 'Fira Code', monospace; font-size: 13px; line-height: 1.6; }
            .wua-action-card { background: white; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
            .wua-action-card h4 { margin: 0 0 10px 0; color: #18181b; display: flex; align-items: center; gap: 10px; }
            .wua-action-card h4 code { background: #059669; color: #fff; padding: 4px 10px; border-radius: 4px; }
            .wua-params-table { width: 100%; font-size: 13px; margin-top: 15px; }
            .wua-params-table th { text-align: left; padding: 8px; background: #fafafa; }
            .wua-params-table td { padding: 8px; border-bottom: 1px solid #e5e5e5; }
            .wua-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; }
            .wua-tag-required { background: #f8d7da; color: #721c24; }
            .wua-tag-optional { background: #d4edda; color: #155724; }
            .wua-copy-btn { background: #3f3f46; color: #fafafa; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
            .wua-copy-btn:hover { background: #52525b; }
            .wua-tabs-code { display: flex; gap: 10px; margin-bottom: 10px; }
            .wua-tabs-code button { padding: 8px 16px; border: none; background: #27272a; color: #a1a1aa; cursor: pointer; border-radius: 4px 4px 0 0; }
            .wua-tabs-code button.active { background: #18181b; color: #fafafa; }
            
            /* Custom notice styles within our design */
            .wua-notice { padding: 15px 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid; display: flex; align-items: center; gap: 12px; }
            .wua-notice.success { background: #d4edda; border-color: #28a745; color: #155724; }
            .wua-notice.error { background: #f8d7da; border-color: #dc3545; color: #721c24; }
            .wua-notice.warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
            .wua-notice.info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
            .wua-notice .dashicons { flex-shrink: 0; }
        </style>
        
        <div class="wrap wua-wrap wp-update-agent-page">
            <h1 style="display:none;"></h1>
            <div class="wua-header">
                <h1><img src="<?php echo esc_url(WP_UPDATE_AGENT_PLUGIN_URL . 'assets/wp-sentinel-favicon.svg'); ?>" alt="WP Sentinel"><?php esc_html_e('WP Sentinel Agent plugin', 'wp-update-agent'); ?></h1>
                <p><?php esc_html_e('Secure REST API for managing WordPress updates, plugin installations, and SMTP testing.', 'wp-update-agent'); ?></p>
            </div>
            
            <nav class="wua-tabs">
                <?php foreach ($tabs as $tab_id => $tab): ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-update-agent&tab=' . $tab_id)); ?>" 
                       class="wua-tab <?php echo $current_tab === $tab_id ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
                        <?php echo esc_html($tab['label']); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="wua-content">
                <?php
                // Display notices within our custom design
                if (!empty($notices)) {
                    foreach ($notices as $notice) {
                        $icon = $notice['type'] === 'success' ? 'yes-alt' : 
                               ($notice['type'] === 'error' ? 'warning' : 'info');
                        echo '<div class="wua-notice ' . esc_attr($notice['type']) . '">';
                        echo '<span class="dashicons dashicons-' . esc_attr($icon) . '"></span>';
                        echo '<span>' . esc_html($notice['message']) . '</span>';
                        echo '</div>';
                    }
                }
                
                switch ($current_tab) {
                    case 'dashboard':
                        self::render_dashboard_tab();
                        break;
                    case 'settings':
                        self::render_settings_tab();
                        break;
                    case 'test':
                        self::render_test_tab();
                        break;
                    case 'logs':
                        self::render_logs_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Dashboard tab
     */
    private static function render_dashboard_tab() {
        $lock_status = WP_Update_Agent_Locker::get_status();
        $secret = get_option('wp_update_agent_secret', '');
        $logs = WP_Update_Agent_Logger::get_recent_logs(5);
        
        // Get some stats
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $all_plugins = get_plugins();
        $update_plugins = get_site_transient('update_plugins');
        $plugins_with_updates = isset($update_plugins->response) ? count($update_plugins->response) : 0;
        ?>
        
        <div class="wua-grid" style="margin-bottom: 25px;">
            <div class="wua-stat">
                <div class="wua-stat-value"><?php echo count($all_plugins); ?></div>
                <div class="wua-stat-label"><?php esc_html_e('Installed Plugins', 'wp-update-agent'); ?></div>
            </div>
            <div class="wua-stat">
                <div class="wua-stat-value" style="color: <?php echo $plugins_with_updates > 0 ? '#ffc107' : '#28a745'; ?>">
                    <?php echo $plugins_with_updates; ?>
                </div>
                <div class="wua-stat-label"><?php esc_html_e('Updates Available', 'wp-update-agent'); ?></div>
            </div>
            <div class="wua-stat">
                <div class="wua-stat-value"><?php echo esc_html(get_bloginfo('version')); ?></div>
                <div class="wua-stat-label"><?php esc_html_e('WordPress Version', 'wp-update-agent'); ?></div>
            </div>
            <div class="wua-stat">
                <div class="wua-stat-value">
                    <span class="wua-status-dot <?php echo $lock_status ? 'wua-status-yellow' : 'wua-status-green'; ?>"></span>
                    <?php echo $lock_status ? __('Locked', 'wp-update-agent') : __('Ready', 'wp-update-agent'); ?>
                </div>
                <div class="wua-stat-label"><?php esc_html_e('Agent Status', 'wp-update-agent'); ?></div>
            </div>
        </div>
        
        <div class="wua-card">
            <h3><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('API Endpoints', 'wp-update-agent'); ?></h3>
            
            <div class="wua-endpoint">
                <span class="wua-method wua-method-post">POST</span>
                <code><?php echo esc_url(rest_url('agent/v1/execute')); ?></code>
                <span style="color: #666; margin-left: 15px;"><?php esc_html_e('Main execute endpoint', 'wp-update-agent'); ?></span>
            </div>
            <div class="wua-endpoint">
                <span class="wua-method wua-method-post">POST</span>
                <code><?php echo esc_url(rest_url('agent/v1/test-smtp')); ?></code>
                <span style="color: #666; margin-left: 15px;"><?php esc_html_e('SMTP test endpoint', 'wp-update-agent'); ?></span>
            </div>
            <div class="wua-endpoint">
                <span class="wua-method wua-method-post">POST</span>
                <code><?php echo esc_url(rest_url('agent/v1/status')); ?></code>
                <span style="color: #666; margin-left: 15px;"><?php esc_html_e('Agent status endpoint', 'wp-update-agent'); ?></span>
            </div>
            <div class="wua-endpoint">
                <span class="wua-method wua-method-post">POST</span>
                <code><?php echo esc_url(rest_url('agent/v1/configure')); ?></code>
                <span style="color: #666; margin-left: 15px;"><?php esc_html_e('Configure secret key (admin auth)', 'wp-update-agent'); ?></span>
            </div>
        </div>
        
        <div class="wua-card">
            <h3><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e('Quick Status', 'wp-update-agent'); ?></h3>
            
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 10px 0;"><strong><?php esc_html_e('Secret Configured', 'wp-update-agent'); ?></strong></td>
                    <td>
                        <?php if (!empty($secret)): ?>
                            <span class="wua-status-dot wua-status-green"></span> <?php esc_html_e('Yes', 'wp-update-agent'); ?>
                        <?php else: ?>
                            <span class="wua-status-dot wua-status-red"></span> <?php esc_html_e('No - Please configure in Settings', 'wp-update-agent'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px 0;"><strong><?php esc_html_e('Lock Status', 'wp-update-agent'); ?></strong></td>
                    <td>
                        <?php if ($lock_status): ?>
                            <span class="wua-status-dot wua-status-yellow"></span>
                            <?php printf(
                                esc_html__('Locked by: %s (expires in %d seconds)', 'wp-update-agent'),
                                esc_html($lock_status['action']),
                                intval($lock_status['remaining_seconds'])
                            ); ?>
                        <?php else: ?>
                            <span class="wua-status-dot wua-status-green"></span> <?php esc_html_e('No active lock', 'wp-update-agent'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php if (!empty($logs)): ?>
        <div class="wua-card">
            <h3><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Recent Activity', 'wp-update-agent'); ?></h3>
            <table class="wua-logs-table">
                <thead>
                    <tr>
                        <th style="width: 150px;"><?php esc_html_e('Time', 'wp-update-agent'); ?></th>
                        <th style="width: 80px;"><?php esc_html_e('Level', 'wp-update-agent'); ?></th>
                        <th><?php esc_html_e('Message', 'wp-update-agent'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><small><?php echo esc_html($log['timestamp']); ?></small></td>
                        <td>
                            <span class="wua-log-level wua-log-<?php echo strtolower($log['level']); ?>">
                                <?php echo esc_html($log['level']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['message']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render Settings tab
     */
    private static function render_settings_tab() {
        $secret = get_option('wp_update_agent_secret', '');
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::OPTION_GROUP); ?>
            
            <div class="wua-card">
                <h3><span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('API Authentication', 'wp-update-agent'); ?></h3>
                
                <p><?php esc_html_e('This secret key is used for HMAC-SHA256 authentication. Keep it secure and only share with trusted applications.', 'wp-update-agent'); ?></p>
                
                <div style="margin: 20px 0;">
                    <label for="wp_update_agent_secret" style="display: block; margin-bottom: 8px; font-weight: 600;">
                        <?php esc_html_e('Shared Secret', 'wp-update-agent'); ?>
                    </label>
                    <input type="text" 
                           name="wp_update_agent_secret" 
                           id="wp_update_agent_secret" 
                           value="<?php echo esc_attr($secret); ?>" 
                           class="wua-secret-field"
                    />
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" id="generate-secret" class="wua-btn wua-btn-secondary">
                        <span class="dashicons dashicons-randomize"></span>
                        <?php esc_html_e('Generate New Secret', 'wp-update-agent'); ?>
                    </button>
                    <button type="button" id="copy-secret" class="wua-btn wua-btn-secondary">
                        <span class="dashicons dashicons-clipboard"></span>
                        <?php esc_html_e('Copy to Clipboard', 'wp-update-agent'); ?>
                    </button>
                </div>
            </div>
            
            <?php submit_button(__('Save Settings', 'wp-update-agent'), 'wua-btn wua-btn-primary', 'submit', false); ?>
        </form>
        
        <?php if (WP_Update_Agent_Locker::is_locked()): ?>
        <div class="wua-card" style="margin-top: 30px; border-color: #ffc107;">
            <h3><span class="dashicons dashicons-lock"></span> <?php esc_html_e('Lock Management', 'wp-update-agent'); ?></h3>
            <p><?php esc_html_e('An operation lock is currently active. You can force release it if needed.', 'wp-update-agent'); ?></p>
            <form method="post">
                <?php wp_nonce_field('wp_update_agent_force_unlock'); ?>
                <button type="submit" name="force_unlock" class="wua-btn wua-btn-danger" 
                        onclick="return confirm('<?php esc_attr_e('Are you sure? This may cause issues if an operation is still running.', 'wp-update-agent'); ?>');">
                    <span class="dashicons dashicons-unlock"></span>
                    <?php esc_html_e('Force Release Lock', 'wp-update-agent'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <script>
        document.getElementById('generate-secret').addEventListener('click', function() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let secret = '';
            for (let i = 0; i < 64; i++) {
                secret += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('wp_update_agent_secret').value = secret;
        });
        
        document.getElementById('copy-secret').addEventListener('click', function() {
            const field = document.getElementById('wp_update_agent_secret');
            field.select();
            document.execCommand('copy');
            this.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
            setTimeout(() => {
                this.innerHTML = '<span class="dashicons dashicons-clipboard"></span> Copy to Clipboard';
            }, 2000);
        });
        </script>
        <?php
    }
    
    /**
     * Render Test tab
     */
    private static function render_test_tab() {
        ?>
        <div class="wua-card">
            <h3><span class="dashicons dashicons-code-standards"></span> <?php esc_html_e('API Test Console', 'wp-update-agent'); ?></h3>
            <p><?php esc_html_e('Test API actions directly. This bypasses HMAC authentication for internal testing.', 'wp-update-agent'); ?></p>
        </div>
        
        <div class="wua-test-console">
            <select id="test-action" class="wua-test-select">
                <optgroup label="<?php esc_attr_e('Plugin Actions', 'wp-update-agent'); ?>">
                    <option value="plugin_list">plugin_list - List all plugins</option>
                    <option value="update_plugin">update_plugin - Update specific plugin</option>
                    <option value="update_all_plugins">update_all_plugins - Update all plugins</option>
                    <option value="install_plugin_slug">install_plugin_slug - Install from WordPress.org</option>
                    <option value="activate_plugin">activate_plugin - Activate plugin</option>
                    <option value="deactivate_plugin">deactivate_plugin - Deactivate plugin</option>
                    <option value="delete_plugin">delete_plugin - Delete plugin</option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Theme Actions', 'wp-update-agent'); ?>">
                    <option value="theme_list">theme_list - List all themes</option>
                    <option value="update_theme">update_theme - Update specific theme</option>
                    <option value="update_all_themes">update_all_themes - Update all themes</option>
                    <option value="install_theme_slug">install_theme_slug - Install from WordPress.org</option>
                    <option value="activate_theme">activate_theme - Activate theme</option>
                    <option value="delete_theme">delete_theme - Delete theme</option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('Core Actions', 'wp-update-agent'); ?>">
                    <option value="core_check">core_check - Check for updates</option>
                    <option value="core_update">core_update - Update WordPress</option>
                    <option value="language_update">language_update - Update languages</option>
                    <option value="system_status">system_status - System info</option>
                </optgroup>
                <optgroup label="<?php esc_attr_e('SMTP Actions', 'wp-update-agent'); ?>">
                    <option value="smtp_test">smtp_test - Send test email</option>
                    <option value="smtp_info">smtp_info - SMTP configuration</option>
                </optgroup>
            </select>
            
            <div id="param-fields">
                <input type="text" id="param-slug" class="wua-test-input" placeholder="Plugin or theme slug (e.g., akismet, twentytwentyfour)" style="display: none;">
                <input type="email" id="param-email" class="wua-test-input" placeholder="Email address (optional)" style="display: none;">
                <label id="param-activate-wrap" class="wua-test-checkbox" style="display: none;">
                    <input type="checkbox" id="param-activate"> <?php esc_html_e('Activate after installation', 'wp-update-agent'); ?>
                </label>
            </div>
            
            <button type="button" id="run-test" class="wua-btn wua-btn-primary" style="width: 100%; justify-content: center; padding: 15px; font-size: 15px;">
                <span class="dashicons dashicons-controls-play"></span>
                <?php esc_html_e('Run Test', 'wp-update-agent'); ?>
            </button>
            
            <div id="test-result" style="display: none; margin-top: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <span style="color: #666; font-size: 13px; font-weight: 600;"><?php esc_html_e('Response:', 'wp-update-agent'); ?></span>
                    <button type="button" class="wua-btn wua-btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="copyTestOutput()">
                        <span class="dashicons dashicons-clipboard" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Copy', 'wp-update-agent'); ?>
                    </button>
                </div>
                <pre id="test-output" class="wua-test-output"></pre>
            </div>
        </div>
        
        <script>
        (function() {
            const actionSelect = document.getElementById('test-action');
            const slugField = document.getElementById('param-slug');
            const emailField = document.getElementById('param-email');
            const activateWrap = document.getElementById('param-activate-wrap');
            const runBtn = document.getElementById('run-test');
            const resultDiv = document.getElementById('test-result');
            const outputPre = document.getElementById('test-output');
            
            const needsSlug = ['update_plugin', 'install_plugin_slug', 'activate_plugin', 'deactivate_plugin', 'delete_plugin', 'update_theme', 'install_theme_slug', 'activate_theme', 'delete_theme'];
            const needsActivate = ['install_plugin_slug', 'install_theme_slug'];
            const needsEmail = ['smtp_test'];
            
            actionSelect.addEventListener('change', function() {
                const action = this.value;
                slugField.style.display = needsSlug.includes(action) ? 'block' : 'none';
                activateWrap.style.display = needsActivate.includes(action) ? 'block' : 'none';
                emailField.style.display = needsEmail.includes(action) ? 'block' : 'none';
            });
            
            runBtn.addEventListener('click', function() {
                const action = actionSelect.value;
                const params = {};
                
                if (needsSlug.includes(action)) {
                    const slug = slugField.value.trim();
                    if (!slug) {
                        alert('Please enter a plugin slug');
                        return;
                    }
                    params.slug = slug;
                }
                
                if (needsActivate.includes(action)) {
                    params.activate = document.getElementById('param-activate').checked;
                }
                
                if (needsEmail.includes(action) && emailField.value.trim()) {
                    params.to = emailField.value.trim();
                }
                
                runBtn.disabled = true;
                runBtn.innerHTML = '<span class="spinner is-active" style="margin: 0;"></span> Running...';
                resultDiv.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'wp_update_agent_test');
                formData.append('nonce', '<?php echo wp_create_nonce('wp_update_agent_test'); ?>');
                formData.append('test_action', action);
                formData.append('params', JSON.stringify(params));
                
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    runBtn.disabled = false;
                    runBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span> Run Test';
                    resultDiv.style.display = 'block';
                    
                    const result = data.success ? data.data : data;
                    outputPre.textContent = JSON.stringify(result, null, 2);
                    
                    // Syntax highlighting for status
                    if (result.status === 'success') {
                        outputPre.style.borderLeft = '4px solid #28a745';
                    } else if (result.status === 'partial') {
                        outputPre.style.borderLeft = '4px solid #ffc107';
                    } else {
                        outputPre.style.borderLeft = '4px solid #dc3545';
                    }
                })
                .catch(err => {
                    runBtn.disabled = false;
                    runBtn.innerHTML = '<span class="dashicons dashicons-controls-play"></span> Run Test';
                    resultDiv.style.display = 'block';
                    outputPre.textContent = 'Error: ' + err.message;
                    outputPre.style.borderLeft = '4px solid #dc3545';
                });
            });
        })();
        
        function copyTestOutput() {
            const output = document.getElementById('test-output').textContent;
            navigator.clipboard.writeText(output);
        }
        </script>
        <?php
    }
    
    /**
     * Render Logs tab
     */
    private static function render_logs_tab() {
        $logs = WP_Update_Agent_Logger::get_recent_logs(50);
        ?>
        <div class="wua-card">
            <h3 style="display: flex; justify-content: space-between; align-items: center;">
                <span><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Activity Logs', 'wp-update-agent'); ?></span>
                <form method="post" style="margin: 0;">
                    <?php wp_nonce_field('wp_update_agent_clear_logs'); ?>
                    <button type="submit" name="clear_logs" class="wua-btn wua-btn-secondary" style="padding: 8px 15px;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear Logs', 'wp-update-agent'); ?>
                    </button>
                </form>
            </h3>
            
            <?php if (empty($logs)): ?>
                <p style="text-align: center; color: #666; padding: 40px;">
                    <span class="dashicons dashicons-info" style="font-size: 40px; width: 40px; height: 40px; color: #ddd;"></span><br><br>
                    <?php esc_html_e('No logs available yet. Logs will appear here after API requests are made.', 'wp-update-agent'); ?>
                </p>
            <?php else: ?>
                <table class="wua-logs-table">
                    <thead>
                        <tr>
                            <th style="width: 160px;"><?php esc_html_e('Time', 'wp-update-agent'); ?></th>
                            <th style="width: 80px;"><?php esc_html_e('Level', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('Message', 'wp-update-agent'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small><?php echo esc_html($log['timestamp']); ?></small></td>
                            <td>
                                <span class="wua-log-level wua-log-<?php echo strtolower($log['level']); ?>">
                                    <?php echo esc_html($log['level']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render Docs tab
     */
    private static function render_docs_tab() {
        $docs = self::get_api_documentation();
        ?>
        
        <div style="margin-bottom: 20px;">
            <button type="button" class="wua-btn wua-btn-primary" onclick="downloadDocs()">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Download API Docs (JSON)', 'wp-update-agent'); ?>
            </button>
        </div>
        
        <!-- Authentication -->
        <div class="wua-docs-section">
            <h3><span class="dashicons dashicons-shield-alt"></span> <?php esc_html_e('Authentication', 'wp-update-agent'); ?></h3>
            
            <div class="wua-card">
                <p><?php esc_html_e('All requests must include HMAC-SHA256 authentication headers:', 'wp-update-agent'); ?></p>
                
                <table class="wua-params-table" style="margin-top: 15px;">
                    <tr>
                        <th><?php esc_html_e('Header', 'wp-update-agent'); ?></th>
                        <th><?php esc_html_e('Description', 'wp-update-agent'); ?></th>
                    </tr>
                    <tr>
                        <td><code>X-Agent-Timestamp</code></td>
                        <td><?php esc_html_e('Unix timestamp in seconds', 'wp-update-agent'); ?></td>
                    </tr>
                    <tr>
                        <td><code>X-Agent-Signature</code></td>
                        <td><?php esc_html_e('HMAC-SHA256(timestamp + "." + body, secret)', 'wp-update-agent'); ?></td>
                    </tr>
                    <tr>
                        <td><code>Content-Type</code></td>
                        <td><code>application/json</code></td>
                    </tr>
                </table>
                
                <p style="margin-top: 15px; color: #666;">
                    <strong><?php esc_html_e('Note:', 'wp-update-agent'); ?></strong>
                    <?php esc_html_e('Timestamps older than 5 minutes will be rejected.', 'wp-update-agent'); ?>
                </p>
            </div>
        </div>
        
        <!-- Endpoints -->
        <div class="wua-docs-section">
            <h3><span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('Endpoints', 'wp-update-agent'); ?></h3>
            
            <?php foreach ($docs['endpoints'] as $endpoint): ?>
            <div class="wua-endpoint" style="background: #f8f9fa;">
                <span class="wua-method wua-method-post"><?php echo esc_html($endpoint['method']); ?></span>
                <code><?php echo esc_html($endpoint['url']); ?></code>
                <p style="margin: 10px 0 0 0; color: #666;"><?php echo esc_html($endpoint['description']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Actions -->
        <div class="wua-docs-section">
            <h3><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('Available Actions', 'wp-update-agent'); ?></h3>
            
            <?php 
            $categories = array(
                'plugins' => __('Plugin Actions', 'wp-update-agent'),
                'themes' => __('Theme Actions', 'wp-update-agent'),
                'core' => __('Core Actions', 'wp-update-agent'),
                'smtp' => __('SMTP Actions', 'wp-update-agent'),
            );
            
            foreach ($categories as $cat_key => $cat_label): 
                $cat_actions = array_filter($docs['actions'], function($a) use ($cat_key) {
                    return $a['category'] === $cat_key;
                });
            ?>
            
            <h4 style="margin: 25px 0 15px 0; color: #1c67b0;"><?php echo esc_html($cat_label); ?></h4>
            
            <?php foreach ($cat_actions as $action): ?>
            <div class="wua-action-card">
                <h4>
                    <code><?php echo esc_html($action['name']); ?></code>
                    <span style="font-weight: normal; color: #666; font-size: 14px;"><?php echo esc_html($action['description']); ?></span>
                </h4>
                
                <?php if (!empty($action['parameters'])): ?>
                <table class="wua-params-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Parameter', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('Type', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('Required', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('Description', 'wp-update-agent'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($action['parameters'] as $param): ?>
                        <tr>
                            <td><code><?php echo esc_html($param['name']); ?></code></td>
                            <td><?php echo esc_html($param['type']); ?></td>
                            <td>
                                <span class="wua-tag <?php echo $param['required'] ? 'wua-tag-required' : 'wua-tag-optional'; ?>">
                                    <?php echo $param['required'] ? __('Required', 'wp-update-agent') : __('Optional', 'wp-update-agent'); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($param['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <strong style="font-size: 12px; color: #666;"><?php esc_html_e('Example Request:', 'wp-update-agent'); ?></strong>
                    <pre class="wua-code-block" style="margin-top: 8px; padding: 12px;"><?php echo esc_html(json_encode($action['example_request'], JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Code Examples -->
        <div class="wua-docs-section">
            <h3><span class="dashicons dashicons-editor-code"></span> <?php esc_html_e('Code Examples', 'wp-update-agent'); ?></h3>
            
            <div class="wua-card" style="padding: 0; overflow: hidden;">
                <div class="wua-tabs-code" style="background: #2d2d2d; padding: 10px 15px;">
                    <button class="active" onclick="showCodeTab('typescript')">TypeScript / Next.js</button>
                    <button onclick="showCodeTab('php')">PHP</button>
                    <button onclick="showCodeTab('curl')">cURL</button>
                </div>
                
                <div id="code-typescript" class="code-tab-content">
                    <pre class="wua-code-block" style="margin: 0; border-radius: 0;"><?php echo esc_html($docs['code_examples']['typescript']); ?></pre>
                </div>
                <div id="code-php" class="code-tab-content" style="display: none;">
                    <pre class="wua-code-block" style="margin: 0; border-radius: 0;"><?php echo esc_html($docs['code_examples']['php']); ?></pre>
                </div>
                <div id="code-curl" class="code-tab-content" style="display: none;">
                    <pre class="wua-code-block" style="margin: 0; border-radius: 0;"><?php echo esc_html($docs['code_examples']['curl']); ?></pre>
                </div>
            </div>
        </div>
        
        <!-- Error Codes -->
        <div class="wua-docs-section">
            <h3><span class="dashicons dashicons-warning"></span> <?php esc_html_e('Error Codes', 'wp-update-agent'); ?></h3>
            
            <div class="wua-card">
                <table class="wua-params-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Code', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('HTTP Status', 'wp-update-agent'); ?></th>
                            <th><?php esc_html_e('Description', 'wp-update-agent'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs['error_codes'] as $error): ?>
                        <tr>
                            <td><code><?php echo esc_html($error['code']); ?></code></td>
                            <td><?php echo esc_html($error['status']); ?></td>
                            <td><?php echo esc_html($error['description']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        function showCodeTab(lang) {
            document.querySelectorAll('.code-tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.wua-tabs-code button').forEach(el => el.classList.remove('active'));
            document.getElementById('code-' + lang).style.display = 'block';
            event.target.classList.add('active');
        }
        
        function downloadDocs() {
            const docs = <?php echo wp_json_encode($docs); ?>;
            const blob = new Blob([JSON.stringify(docs, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'wp-update-agent-api-docs.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        </script>
        <?php
    }
}
