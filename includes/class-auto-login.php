<?php
/**
 * Auto Login handler for one-time login tokens
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Auto_Login
 * 
 * Handles generation and validation of one-time login tokens
 */
class WP_Update_Agent_Auto_Login {
    
    /**
     * Option key for storing login tokens
     */
    const OPTION_KEY = 'wp_update_agent_login_tokens';
    
    /**
     * Token expiration in seconds (60 seconds)
     */
    const TOKEN_EXPIRY = 60;
    
    /**
     * Initialize hooks
     */
    public static function init() {
        // Register the login endpoint
        add_action('init', array(__CLASS__, 'register_login_endpoint'));
        add_action('template_redirect', array(__CLASS__, 'handle_login_request'));
        
        // Clean up expired tokens periodically
        add_action('wp_update_agent_cleanup_tokens', array(__CLASS__, 'cleanup_expired_tokens'));
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wp_update_agent_cleanup_tokens')) {
            wp_schedule_event(time(), 'hourly', 'wp_update_agent_cleanup_tokens');
        }
    }
    
    /**
     * Register the login endpoint
     */
    public static function register_login_endpoint() {
        add_rewrite_rule(
            '^wp-sentinel-login/?$',
            'index.php?wp_sentinel_login=1',
            'top'
        );
        add_rewrite_tag('%wp_sentinel_login%', '1');
    }
    
    /**
     * Handle the login request
     */
    public static function handle_login_request() {
        if (!get_query_var('wp_sentinel_login')) {
            return;
        }
        
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
        
        if (empty($token)) {
            wp_die(__('Invalid login request. Token is missing.', 'wp-update-agent'), __('Login Failed', 'wp-update-agent'), array('response' => 400));
        }
        
        // Validate the token
        $result = self::validate_and_consume_token($token);
        
        if (is_wp_error($result)) {
            WP_Update_Agent_Logger::log('error', sprintf('Auto-login failed: %s', $result->get_error_message()));
            wp_die($result->get_error_message(), __('Login Failed', 'wp-update-agent'), array('response' => 403));
        }
        
        $user_id = $result['user_id'];
        
        // Log the user in
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            WP_Update_Agent_Logger::log('error', sprintf('Auto-login failed: User %d not found', $user_id));
            wp_die(__('User not found.', 'wp-update-agent'), __('Login Failed', 'wp-update-agent'), array('response' => 404));
        }
        
        // Check if user is admin
        if (!user_can($user, 'manage_options')) {
            WP_Update_Agent_Logger::log('error', sprintf('Auto-login failed: User %d is not an admin', $user_id));
            wp_die(__('User does not have admin privileges.', 'wp-update-agent'), __('Login Failed', 'wp-update-agent'), array('response' => 403));
        }
        
        // Clear any existing sessions
        wp_clear_auth_cookie();
        
        // Set the auth cookie
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, false);
        
        WP_Update_Agent_Logger::log('info', sprintf('Auto-login successful for user: %s', $user->user_login));
        
        // Redirect to admin dashboard
        $redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : admin_url();
        
        wp_safe_redirect($redirect_to);
        exit;
    }
    
    /**
     * Generate a one-time login token
     *
     * @param int $user_id The user ID to generate token for
     * @return array|WP_Error Token data or error
     */
    public static function generate_token($user_id = null) {
        // If no user ID provided, use the first admin
        if (!$user_id) {
            $admins = get_users(array(
                'role' => 'administrator',
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC',
            ));
            
            if (empty($admins)) {
                return new WP_Error(
                    'no_admin_found',
                    __('No administrator user found.', 'wp-update-agent'),
                    array('status' => 404)
                );
            }
            
            $user_id = $admins[0]->ID;
        }
        
        // Verify user exists and is admin
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return new WP_Error(
                'user_not_found',
                __('User not found.', 'wp-update-agent'),
                array('status' => 404)
            );
        }
        
        if (!user_can($user, 'manage_options')) {
            return new WP_Error(
                'user_not_admin',
                __('User is not an administrator.', 'wp-update-agent'),
                array('status' => 403)
            );
        }
        
        // Generate a secure random token
        $token = wp_generate_password(64, false, false);
        
        // Get existing tokens
        $tokens = get_option(self::OPTION_KEY, array());
        
        // Clean up any expired tokens for this user
        $tokens = array_filter($tokens, function($data) use ($user_id) {
            return $data['user_id'] !== $user_id && $data['expires'] > time();
        });
        
        // Store the new token
        $tokens[$token] = array(
            'user_id' => $user_id,
            'created' => time(),
            'expires' => time() + self::TOKEN_EXPIRY,
        );
        
        update_option(self::OPTION_KEY, $tokens, false);
        
        // Build the login URL
        $login_url = home_url('/wp-sentinel-login/');
        $login_url = add_query_arg('token', $token, $login_url);
        
        WP_Update_Agent_Logger::log('info', sprintf(
            'Generated auto-login token for user: %s (expires in %d seconds)',
            $user->user_login,
            self::TOKEN_EXPIRY
        ));
        
        return array(
            'token' => $token,
            'login_url' => $login_url,
            'expires' => time() + self::TOKEN_EXPIRY,
            'user_login' => $user->user_login,
        );
    }
    
    /**
     * Validate and consume a token (one-time use)
     *
     * @param string $token The token to validate
     * @return array|WP_Error Token data or error
     */
    public static function validate_and_consume_token($token) {
        $tokens = get_option(self::OPTION_KEY, array());
        
        if (!isset($tokens[$token])) {
            return new WP_Error(
                'invalid_token',
                __('Invalid or expired login token.', 'wp-update-agent'),
                array('status' => 403)
            );
        }
        
        $token_data = $tokens[$token];
        
        // Check if token has expired
        if ($token_data['expires'] < time()) {
            // Remove expired token
            unset($tokens[$token]);
            update_option(self::OPTION_KEY, $tokens, false);
            
            return new WP_Error(
                'token_expired',
                __('Login token has expired. Please request a new one.', 'wp-update-agent'),
                array('status' => 403)
            );
        }
        
        // Remove the token (one-time use)
        unset($tokens[$token]);
        update_option(self::OPTION_KEY, $tokens, false);
        
        return $token_data;
    }
    
    /**
     * Cleanup expired tokens
     */
    public static function cleanup_expired_tokens() {
        $tokens = get_option(self::OPTION_KEY, array());
        
        $tokens = array_filter($tokens, function($data) {
            return $data['expires'] > time();
        });
        
        update_option(self::OPTION_KEY, $tokens, false);
    }
    
    /**
     * Flush rewrite rules on activation
     */
    public static function activate() {
        self::register_login_endpoint();
        flush_rewrite_rules();
    }
    
    /**
     * Flush rewrite rules on deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize the auto-login handler
WP_Update_Agent_Auto_Login::init();
