<?php
/**
 * Locker class for preventing concurrent operations
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_Locker
 * 
 * Handles locking to prevent concurrent update operations
 */
class WP_Update_Agent_Locker {
    
    /**
     * Lock option name
     */
    const LOCK_OPTION = 'wp_update_agent_lock';
    
    /**
     * Acquire a lock
     *
     * @param string $action The action requesting the lock
     * @return true|WP_Error
     */
    public static function acquire($action) {
        $lock = get_option(self::LOCK_OPTION);
        
        // Check if lock exists and is still valid
        if ($lock) {
            $lock_time = intval($lock['time']);
            $lock_action = $lock['action'];
            
            // Check if lock has expired
            if ((time() - $lock_time) < WP_UPDATE_AGENT_LOCK_TIMEOUT) {
                WP_Update_Agent_Logger::log('warning', sprintf(
                    'Lock acquisition failed: Lock held by action "%s"',
                    $lock_action
                ));
                
                return new WP_Error(
                    'agent_locked',
                    sprintf(
                        __('Another operation is in progress: %s. Please try again later.', 'wp-update-agent'),
                        $lock_action
                    ),
                    array('status' => 423)
                );
            }
            
            // Lock has expired, we can take it
            WP_Update_Agent_Logger::log('info', sprintf(
                'Expired lock from action "%s" will be replaced',
                $lock_action
            ));
        }
        
        // Acquire the lock
        $new_lock = array(
            'action' => $action,
            'time' => time(),
        );
        
        update_option(self::LOCK_OPTION, $new_lock, false);
        
        WP_Update_Agent_Logger::log('info', sprintf('Lock acquired for action: %s', $action));
        
        return true;
    }
    
    /**
     * Release the lock
     *
     * @param string $action The action releasing the lock
     * @return bool
     */
    public static function release($action) {
        $lock = get_option(self::LOCK_OPTION);
        
        // Only release if we own the lock
        if ($lock && $lock['action'] === $action) {
            delete_option(self::LOCK_OPTION);
            WP_Update_Agent_Logger::log('info', sprintf('Lock released for action: %s', $action));
            return true;
        }
        
        return false;
    }
    
    /**
     * Force release the lock (admin only)
     *
     * @return bool
     */
    public static function force_release() {
        delete_option(self::LOCK_OPTION);
        WP_Update_Agent_Logger::log('warning', 'Lock was force released');
        return true;
    }
    
    /**
     * Get current lock status
     *
     * @return array|false
     */
    public static function get_status() {
        $lock = get_option(self::LOCK_OPTION);
        
        if (!$lock) {
            return false;
        }
        
        $lock_time = intval($lock['time']);
        $elapsed = time() - $lock_time;
        $remaining = max(0, WP_UPDATE_AGENT_LOCK_TIMEOUT - $elapsed);
        
        return array(
            'action' => $lock['action'],
            'locked_at' => date('Y-m-d H:i:s', $lock_time),
            'elapsed_seconds' => $elapsed,
            'remaining_seconds' => $remaining,
            'is_expired' => $remaining === 0,
        );
    }
    
    /**
     * Check if lock is currently held
     *
     * @return bool
     */
    public static function is_locked() {
        $lock = get_option(self::LOCK_OPTION);
        
        if (!$lock) {
            return false;
        }
        
        $lock_time = intval($lock['time']);
        return (time() - $lock_time) < WP_UPDATE_AGENT_LOCK_TIMEOUT;
    }
}
