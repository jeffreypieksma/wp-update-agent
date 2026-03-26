<?php
/**
 * REST API class for handling API endpoints
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_REST_API
 * 
 * Handles all REST API endpoints
 */
class WP_Update_Agent_REST_API {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'agent/v1';
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Main execute endpoint for all actions
        register_rest_route(self::NAMESPACE, '/execute', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_execute'),
            'permission_callback' => array($this, 'verify_authentication'),
            'args' => array(
                'action' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        // Dedicated SMTP test endpoint
        register_rest_route(self::NAMESPACE, '/test-smtp', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_smtp_test'),
            'permission_callback' => array($this, 'verify_authentication'),
            'args' => array(
                'to' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
            ),
        ));
        
        // Status endpoint (for checking if agent is available)
        register_rest_route(self::NAMESPACE, '/status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_status'),
            'permission_callback' => array($this, 'verify_authentication'),
        ));
        
        // Configure endpoint - uses WordPress admin cookie authentication
        // This allows remote configuration of the secret key when logged in as admin
        register_rest_route(self::NAMESPACE, '/configure', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_configure'),
            'permission_callback' => array($this, 'verify_admin_user'),
            'args' => array(
                'secret' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    /**
     * Verify that the current user is a WordPress admin
     * Uses WordPress cookie authentication (for logged-in admin users)
     *
     * @return true|WP_Error
     */
    public function verify_admin_user() {
        // Check if user is logged in and is an admin
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You must be logged in to perform this action.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to perform this action.', 'wp-update-agent'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Handle configure request - set the secret key
     * This endpoint uses WordPress admin cookie authentication
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_configure(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $secret = isset($params['secret']) ? sanitize_text_field($params['secret']) : '';
        
        if (empty($secret)) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Secret key is required',
            ), 400);
        }
        
        // Validate secret key format (must be at least 32 characters)
        if (strlen($secret) < 32) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Secret key must be at least 32 characters',
            ), 400);
        }
        
        // Update the option
        $result = update_option('wp_update_agent_secret', $secret);
        
        if ($result) {
            WP_Update_Agent_Logger::log('info', 'Secret key configured via REST API');
            
            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => 'Secret key configured successfully',
            ), 200);
        } else {
            // Check if the value is the same (no change needed)
            $current = get_option('wp_update_agent_secret', '');
            if ($current === $secret) {
                return new WP_REST_Response(array(
                    'status' => 'success',
                    'message' => 'Secret key is already configured',
                ), 200);
            }
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Failed to save secret key',
            ), 500);
        }
    }
    
    /**
     * Handle documentation request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_docs(WP_REST_Request $request) {
        if (class_exists('WP_Update_Agent_Admin_Settings')) {
            return new WP_REST_Response(WP_Update_Agent_Admin_Settings::get_api_documentation(), 200);
        }
        
        return new WP_REST_Response(array(
            'error' => 'Documentation not available',
        ), 404);
    }
    
    /**
     * Verify request authentication
     *
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    public function verify_authentication(WP_REST_Request $request) {
        $result = WP_Update_Agent_Authenticator::verify_request($request);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Verify admin context
        return WP_Update_Agent_Authenticator::verify_admin_context();
    }
    
    /**
     * Handle the main execute endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_execute(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $action = isset($params['action']) ? sanitize_text_field($params['action']) : '';
        
        WP_Update_Agent_Logger::log('info', sprintf('Received action request: %s', $action));
        
        // Define allowed actions
        $allowed_actions = array(
            'plugin_list',
            'update_plugin',
            'update_all_plugins',
            'install_plugin_slug',
            'install_plugin_zip',
            'activate_plugin',
            'deactivate_plugin',
            'delete_plugin',
            'theme_list',
            'update_theme',
            'update_all_themes',
            'install_theme_slug',
            'install_theme_zip',
            'activate_theme',
            'delete_theme',
            'core_check',
            'core_update',
            'language_update',
            'smtp_test',
            'smtp_info',
            'system_status',
            'auto_login',
        );
        
        if (!in_array($action, $allowed_actions)) {
            WP_Update_Agent_Logger::log('error', sprintf('Unknown action: %s', $action));
            
            return new WP_REST_Response(array(
                'status' => 'error',
                'action' => $action,
                'logs' => sprintf('Unknown action: %s', $action),
            ), 400);
        }
        
        // Actions that don't require locking
        $no_lock_actions = array('plugin_list', 'theme_list', 'core_check', 'smtp_info', 'system_status', 'auto_login');
        
        // Acquire lock for write operations
        if (!in_array($action, $no_lock_actions)) {
            $lock_result = WP_Update_Agent_Locker::acquire($action);
            
            if (is_wp_error($lock_result)) {
                return new WP_REST_Response(array(
                    'status' => 'error',
                    'action' => $action,
                    'logs' => $lock_result->get_error_message(),
                ), $lock_result->get_error_data()['status'] ?? 423);
            }
        }
        
        try {
            $result = $this->execute_action($action, $params);
        } catch (Exception $e) {
            $result = array(
                'status' => 'error',
                'action' => $action,
                'logs' => sprintf('Exception: %s', $e->getMessage()),
            );
        }
        
        // Release lock
        if (!in_array($action, $no_lock_actions)) {
            WP_Update_Agent_Locker::release($action);
        }
        
        $status_code = ($result['status'] === 'success') ? 200 : 
                       (($result['status'] === 'partial') ? 207 : 400);
        
        return new WP_REST_Response($result, $status_code);
    }
    
    /**
     * Execute the requested action
     *
     * @param string $action
     * @param array $params
     * @return array
     */
    private function execute_action($action, $params) {
        switch ($action) {
            case 'plugin_list':
                return WP_Update_Agent_Plugin_Handler::get_plugin_list();
                
            case 'update_plugin':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Plugin_Handler::update_plugin($slug);
                
            case 'update_all_plugins':
                return WP_Update_Agent_Plugin_Handler::update_all_plugins();
                
            case 'install_plugin_slug':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                $activate = isset($params['activate']) ? (bool) $params['activate'] : false;
                return WP_Update_Agent_Plugin_Handler::install_plugin_slug($slug, $activate);
                
            case 'install_plugin_zip':
                $file_data = isset($params['file']) ? $params['file'] : array();
                $activate = isset($params['activate']) ? (bool) $params['activate'] : false;
                return WP_Update_Agent_Plugin_Handler::install_plugin_zip($file_data, $activate);
                
            case 'activate_plugin':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Plugin_Handler::activate_plugin($slug);
                
            case 'deactivate_plugin':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Plugin_Handler::deactivate_plugin($slug);
                
            case 'delete_plugin':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Plugin_Handler::delete_plugin($slug);
                
            case 'theme_list':
                return WP_Update_Agent_Theme_Handler::get_theme_list();
                
            case 'update_theme':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Theme_Handler::update_theme($slug);
                
            case 'update_all_themes':
                return WP_Update_Agent_Theme_Handler::update_all_themes();
                
            case 'install_theme_slug':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                $activate = isset($params['activate']) ? (bool) $params['activate'] : false;
                return WP_Update_Agent_Theme_Handler::install_theme_slug($slug, $activate);
                
            case 'install_theme_zip':
                $file_data = isset($params['file']) ? $params['file'] : array();
                $activate = isset($params['activate']) ? (bool) $params['activate'] : false;
                return WP_Update_Agent_Theme_Handler::install_theme_zip($file_data, $activate);
                
            case 'activate_theme':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Theme_Handler::activate_theme($slug);
                
            case 'delete_theme':
                $slug = isset($params['slug']) ? sanitize_text_field($params['slug']) : '';
                return WP_Update_Agent_Theme_Handler::delete_theme($slug);
                
            case 'core_check':
                return WP_Update_Agent_Core_Handler::core_check();
                
            case 'core_update':
                $version = isset($params['version']) ? sanitize_text_field($params['version']) : null;
                return WP_Update_Agent_Core_Handler::core_update($version);
                
            case 'language_update':
                return WP_Update_Agent_Core_Handler::language_update();
                
            case 'smtp_test':
                $to = isset($params['to']) ? sanitize_email($params['to']) : null;
                return WP_Update_Agent_SMTP_Handler::test_smtp($to);
                
            case 'smtp_info':
                return WP_Update_Agent_SMTP_Handler::get_smtp_info();
                
            case 'system_status':
                return WP_Update_Agent_Core_Handler::system_status();
                
            case 'auto_login':
                $user_id = isset($params['user_id']) ? intval($params['user_id']) : null;
                
                // Check if the auto-login class exists
                if (!class_exists('WP_Update_Agent_Auto_Login')) {
                    WP_Update_Agent_Logger::log('error', 'Auto-login class not loaded');
                    return array(
                        'status' => 'error',
                        'action' => $action,
                        'logs' => 'Auto-login functionality is not available. Please update the plugin.',
                    );
                }
                
                WP_Update_Agent_Logger::log('info', sprintf('Generating auto-login token for user_id: %s', $user_id ?: 'auto'));
                
                $result = WP_Update_Agent_Auto_Login::generate_token($user_id);
                
                if (is_wp_error($result)) {
                    WP_Update_Agent_Logger::log('error', sprintf('Auto-login token generation failed: %s', $result->get_error_message()));
                    return array(
                        'status' => 'error',
                        'action' => $action,
                        'logs' => $result->get_error_message(),
                    );
                }
                
                WP_Update_Agent_Logger::log('info', sprintf('Auto-login token generated successfully for user: %s', $result['user_login']));
                
                return array(
                    'status' => 'success',
                    'action' => $action,
                    'login_url' => $result['login_url'],
                    'expires' => $result['expires'],
                    'user_login' => $result['user_login'],
                    'logs' => sprintf('Login token generated for user: %s', $result['user_login']),
                );
                
            default:
                return array(
                    'status' => 'error',
                    'action' => $action,
                    'logs' => sprintf('Unhandled action: %s', $action),
                );
        }
    }
    
    /**
     * Handle the dedicated SMTP test endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_smtp_test(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $to = isset($params['to']) ? sanitize_email($params['to']) : null;
        
        WP_Update_Agent_Logger::log('info', 'Received SMTP test request');
        
        $result = WP_Update_Agent_SMTP_Handler::test_smtp($to);
        
        $status_code = ($result['status'] === 'success') ? 200 : 400;
        
        return new WP_REST_Response($result, $status_code);
    }
    
    /**
     * Handle the status endpoint
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_status(WP_REST_Request $request) {
        $lock_status = WP_Update_Agent_Locker::get_status();
        
        $response = array(
            'status' => 'success',
            'action' => 'status',
            'agent_version' => WP_UPDATE_AGENT_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'is_locked' => WP_Update_Agent_Locker::is_locked(),
            'lock_info' => $lock_status,
            'logs' => 'Agent status retrieved',
        );
        
        return new WP_REST_Response($response, 200);
    }
}
