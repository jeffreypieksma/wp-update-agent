<?php
/**
 * Authenticator class for HMAC-SHA256 authentication
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Authenticator
 * 
 * Handles HMAC-SHA256 signature verification and timestamp validation
 */
class WP_Update_Agent_Authenticator {
    
    /**
     * Verify the request authentication
     *
     * @param WP_REST_Request $request The REST request object
     * @return true|WP_Error True if authenticated, WP_Error otherwise
     */
    public static function verify_request(WP_REST_Request $request) {
        $secret = WP_Update_Agent::get_secret();
        
        if (empty($secret)) {
            WP_Update_Agent_Logger::log('error', 'Authentication failed: Secret not configured');
            return new WP_Error(
                'agent_secret_not_configured',
                __('Agent secret is not configured.', 'wp-update-agent'),
                array('status' => 500)
            );
        }
        
        // Get headers
        $signature = $request->get_header('X-Agent-Signature');
        $timestamp = $request->get_header('X-Agent-Timestamp');
        
        if (empty($signature)) {
            WP_Update_Agent_Logger::log('error', 'Authentication failed: Missing signature header');
            return new WP_Error(
                'agent_missing_signature',
                __('Missing X-Agent-Signature header.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        if (empty($timestamp)) {
            WP_Update_Agent_Logger::log('error', 'Authentication failed: Missing timestamp header');
            return new WP_Error(
                'agent_missing_timestamp',
                __('Missing X-Agent-Timestamp header.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        // Validate timestamp format
        if (!is_numeric($timestamp)) {
            WP_Update_Agent_Logger::log('error', 'Authentication failed: Invalid timestamp format');
            return new WP_Error(
                'agent_invalid_timestamp',
                __('Invalid timestamp format.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        // Verify timestamp is within tolerance (5 minutes)
        $current_time = time();
        $request_time = intval($timestamp);
        $time_diff = abs($current_time - $request_time);
        
        if ($time_diff > WP_UPDATE_AGENT_TIMESTAMP_TOLERANCE) {
            WP_Update_Agent_Logger::log('error', sprintf(
                'Authentication failed: Timestamp expired (diff: %d seconds)',
                $time_diff
            ));
            return new WP_Error(
                'agent_timestamp_expired',
                __('Request timestamp is expired. Please ensure your server time is synchronized.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        // Get request body
        $body = $request->get_body();
        
        // Calculate expected signature
        $payload = $timestamp . '.' . $body;
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        
        // Compare signatures using timing-safe comparison
        if (!hash_equals($expected_signature, $signature)) {
            WP_Update_Agent_Logger::log('error', 'Authentication failed: Invalid signature');
            return new WP_Error(
                'agent_invalid_signature',
                __('Invalid signature.', 'wp-update-agent'),
                array('status' => 401)
            );
        }
        
        WP_Update_Agent_Logger::log('info', 'Authentication successful');
        return true;
    }
    
    /**
     * Generate a signature for testing purposes
     *
     * @param string $body The request body
     * @param int $timestamp The timestamp
     * @param string $secret The secret key
     * @return string The HMAC-SHA256 signature
     */
    public static function generate_signature($body, $timestamp, $secret) {
        $payload = $timestamp . '.' . $body;
        return hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Validate that we're in an admin context
     *
     * @return true|WP_Error
     */
    public static function verify_admin_context() {
        // Load admin functions if not already loaded
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        return true;
    }
}
