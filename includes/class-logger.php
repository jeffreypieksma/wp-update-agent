<?php
/**
 * Logger class for action logging
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Logger
 * 
 * Handles logging of all agent actions
 */
class WP_Update_Agent_Logger {
    
    /**
     * Log file path
     */
    private static $log_file = null;
    
    /**
     * Maximum log file size (5MB)
     */
    const MAX_LOG_SIZE = 5242880;
    
    /**
     * Maximum number of log entries in transient
     */
    const MAX_TRANSIENT_ENTRIES = 100;
    
    /**
     * Get log file path
     *
     * @return string
     */
    private static function get_log_file() {
        if (is_null(self::$log_file)) {
            $log_dir = WP_UPDATE_AGENT_PLUGIN_DIR . 'logs';
            
            // Ensure log directory exists
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
            
            self::$log_file = $log_dir . '/agent-' . date('Y-m') . '.log';
        }
        
        return self::$log_file;
    }
    
    /**
     * Log a message
     *
     * @param string $level Log level (info, warning, error, debug)
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public static function log($level, $message, $context = array()) {
        $timestamp = current_time('mysql');
        $level = strtoupper($level);
        
        // Format message
        $formatted_message = sprintf(
            '[%s] [%s] %s',
            $timestamp,
            $level,
            $message
        );
        
        if (!empty($context)) {
            $formatted_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        $formatted_message .= PHP_EOL;
        
        // Log to file
        self::log_to_file($formatted_message);
        
        // Log to transient for admin display
        self::log_to_transient($level, $message, $context, $timestamp);
    }
    
    /**
     * Log to file
     *
     * @param string $message
     */
    private static function log_to_file($message) {
        $log_file = self::get_log_file();
        
        // Rotate log if too large
        if (file_exists($log_file) && filesize($log_file) > self::MAX_LOG_SIZE) {
            $archive_file = str_replace('.log', '-' . time() . '.log', $log_file);
            rename($log_file, $archive_file);
        }
        
        // Write to file
        error_log($message, 3, $log_file);
    }
    
    /**
     * Log to transient for admin display
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @param string $timestamp
     */
    private static function log_to_transient($level, $message, $context, $timestamp) {
        $logs = get_transient('wp_update_agent_logs');
        
        if (!is_array($logs)) {
            $logs = array();
        }
        
        // Add new entry at the beginning
        array_unshift($logs, array(
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => $timestamp,
        ));
        
        // Limit entries
        if (count($logs) > self::MAX_TRANSIENT_ENTRIES) {
            $logs = array_slice($logs, 0, self::MAX_TRANSIENT_ENTRIES);
        }
        
        // Store for 7 days
        set_transient('wp_update_agent_logs', $logs, 7 * DAY_IN_SECONDS);
    }
    
    /**
     * Get recent logs from transient
     *
     * @param int $limit Number of entries to return
     * @return array
     */
    public static function get_recent_logs($limit = 50) {
        $logs = get_transient('wp_update_agent_logs');
        
        if (!is_array($logs)) {
            return array();
        }
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear all logs from transient
     */
    public static function clear_transient_logs() {
        delete_transient('wp_update_agent_logs');
    }
    
    /**
     * Log an action with structured data
     *
     * @param string $action The action name
     * @param string $status success or error
     * @param array $data Additional data
     */
    public static function log_action($action, $status, $data = array()) {
        $level = ($status === 'success') ? 'info' : 'error';
        $message = sprintf('Action: %s | Status: %s', $action, $status);
        
        self::log($level, $message, $data);
    }
}
