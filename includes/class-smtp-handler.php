<?php
/**
 * SMTP Handler class for testing SMTP configuration
 *
 * @package WP_Update_Agent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WP_Update_Agent_SMTP_Handler
 * 
 * Handles SMTP testing functionality
 */
class WP_Update_Agent_SMTP_Handler {
    
    /**
     * Store PHPMailer errors
     */
    private static $mail_errors = array();
    
    /**
     * Test SMTP configuration by sending a test email
     *
     * @param string|null $to Email address to send test to (defaults to admin_email)
     * @return array
     */
    public static function test_smtp($to = null) {
        // Get recipient email
        if (empty($to)) {
            $to = get_option('admin_email');
        }
        
        // Validate email address
        if (!is_email($to)) {
            WP_Update_Agent_Logger::log_action('smtp_test', 'error', array(
                'error' => 'Invalid email address',
                'to' => $to,
            ));
            
            return array(
                'status' => 'error',
                'action' => 'smtp_test',
                'message' => sprintf('Invalid email address: %s', $to),
                'logs' => 'SMTP test failed: Invalid email address',
            );
        }
        
        // Reset errors
        self::$mail_errors = array();
        
        // Add filter to capture mail failures
        add_action('wp_mail_failed', array(__CLASS__, 'capture_mail_error'));
        
        // Prepare test email
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $timestamp = current_time('mysql');
        
        $subject = sprintf(
            '[%s] SMTP Test - %s',
            $site_name,
            $timestamp
        );
        
        $message = sprintf(
            "This is a test email from your WordPress site.\n\n" .
            "Site: %s\n" .
            "URL: %s\n" .
            "Sent at: %s\n\n" .
            "If you received this email, your SMTP configuration is working correctly.\n\n" .
            "---\n" .
            "This email was sent by WP Update Agent plugin.",
            $site_name,
            $site_url,
            $timestamp
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
        );
        
        // Attempt to send the email
        $result = wp_mail($to, $subject, $message, $headers);
        
        // Remove filter
        remove_action('wp_mail_failed', array(__CLASS__, 'capture_mail_error'));
        
        if ($result) {
            WP_Update_Agent_Logger::log_action('smtp_test', 'success', array(
                'to' => $to,
            ));
            
            return array(
                'status' => 'success',
                'action' => 'smtp_test',
                'message' => sprintf('Test email sent successfully to %s', $to),
                'logs' => sprintf('SMTP test passed. Email sent to %s at %s', $to, $timestamp),
            );
        }
        
        // Collect error information
        $error_message = 'Unknown error';
        
        if (!empty(self::$mail_errors)) {
            $last_error = end(self::$mail_errors);
            if (is_wp_error($last_error)) {
                $error_message = $last_error->get_error_message();
                $error_data = $last_error->get_error_data();
                
                // PHPMailer often includes more details in error_data
                if (is_array($error_data) && isset($error_data['phpmailer_exception_code'])) {
                    $error_message .= sprintf(' (Code: %s)', $error_data['phpmailer_exception_code']);
                }
            }
        }
        
        WP_Update_Agent_Logger::log_action('smtp_test', 'error', array(
            'to' => $to,
            'error' => $error_message,
        ));
        
        return array(
            'status' => 'error',
            'action' => 'smtp_test',
            'message' => sprintf('Failed to send test email to %s', $to),
            'error_details' => $error_message,
            'logs' => sprintf('SMTP test failed: %s', $error_message),
        );
    }
    
    /**
     * Capture mail errors from wp_mail_failed action
     *
     * @param WP_Error $error
     */
    public static function capture_mail_error($error) {
        if (is_wp_error($error)) {
            self::$mail_errors[] = $error;
        }
    }
    
    /**
     * Get SMTP configuration information (for debugging)
     *
     * @return array
     */
    public static function get_smtp_info() {
        global $phpmailer;
        
        // Initialize PHPMailer if needed
        if (!($phpmailer instanceof PHPMailer\PHPMailer\PHPMailer)) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        
        // Get WordPress mail settings
        $from_email = apply_filters('wp_mail_from', get_option('admin_email'));
        $from_name = apply_filters('wp_mail_from_name', get_bloginfo('name'));
        
        $info = array(
            'from_email' => $from_email,
            'from_name' => $from_name,
            'admin_email' => get_option('admin_email'),
            'smtp_configured' => false,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_secure' => null,
        );
        
        // Check if an SMTP plugin has configured PHPMailer via constants
        // These constants are defined by some SMTP plugins
        if (defined('SMTP_HOST') && constant('SMTP_HOST')) {
            $info['smtp_configured'] = true;
            $info['smtp_host'] = constant('SMTP_HOST');
            $info['smtp_port'] = defined('SMTP_PORT') ? constant('SMTP_PORT') : null;
            $info['smtp_secure'] = defined('SMTP_SECURE') ? constant('SMTP_SECURE') : null;
        }
        
        // Check for common SMTP plugins
        $smtp_plugins = array(
            'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
            'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
            'post-smtp/postman-smtp.php' => 'Post SMTP',
            'smtp-mailer/main.php' => 'SMTP Mailer',
            'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
            'mailin/sendinblue.php' => 'Brevo',
            'sendinblue-for-woocommerce/sendinblue-for-woocommerce.php' => 'Brevo WooCommerce',
        );
        
        $active_plugins = get_option('active_plugins', array());
        
        foreach ($smtp_plugins as $plugin_file => $plugin_name) {
            if (in_array($plugin_file, $active_plugins)) {
                $info['smtp_plugin'] = $plugin_name;
                $info['smtp_configured'] = true;
                
                // Try to get actual settings from the plugin
                switch ($plugin_name) {
                    case 'FluentSMTP':
                        $fluent_settings = get_option('fluentmail-settings', array());
                        if (!empty($fluent_settings) && isset($fluent_settings['connections'])) {
                            $connections = $fluent_settings['connections'];
                            // Get the first/default connection
                            $default_connection = isset($fluent_settings['misc']['default_connection']) 
                                ? $fluent_settings['misc']['default_connection'] 
                                : null;
                            
                            $connection = null;
                            if ($default_connection && isset($connections[$default_connection])) {
                                $connection = $connections[$default_connection];
                            } elseif (!empty($connections)) {
                                $connection = reset($connections);
                            }
                            
                            if ($connection && isset($connection['provider_settings'])) {
                                $provider = $connection['provider_settings'];
                                $info['smtp_host'] = isset($provider['host']) ? $provider['host'] : null;
                                $info['smtp_port'] = isset($provider['port']) ? $provider['port'] : null;
                                $info['smtp_secure'] = isset($provider['encryption']) ? $provider['encryption'] : null;
                                $info['smtp_auth'] = isset($provider['auth']) ? ($provider['auth'] === 'yes' || $provider['auth'] === true) : null;
                                $info['smtp_user'] = isset($provider['username']) ? $provider['username'] : null;
                                $info['from_email'] = isset($connection['sender_email']) ? $connection['sender_email'] : $info['from_email'];
                                $info['from_name'] = isset($connection['sender_name']) ? $connection['sender_name'] : $info['from_name'];
                                $info['connection_type'] = isset($provider['provider']) ? $provider['provider'] : 'smtp';
                            }
                        }
                        break;
                        
                    case 'WP Mail SMTP':
                        $wpms_settings = get_option('wp_mail_smtp', array());
                        if (!empty($wpms_settings)) {
                            $mailer = isset($wpms_settings['mail']['mailer']) ? $wpms_settings['mail']['mailer'] : 'mail';
                            $info['connection_type'] = $mailer;
                            if ($mailer === 'smtp' && isset($wpms_settings['smtp'])) {
                                $info['smtp_host'] = isset($wpms_settings['smtp']['host']) ? $wpms_settings['smtp']['host'] : null;
                                $info['smtp_port'] = isset($wpms_settings['smtp']['port']) ? $wpms_settings['smtp']['port'] : null;
                                $info['smtp_secure'] = isset($wpms_settings['smtp']['encryption']) ? $wpms_settings['smtp']['encryption'] : null;
                                $info['smtp_auth'] = isset($wpms_settings['smtp']['auth']) ? (bool)$wpms_settings['smtp']['auth'] : null;
                                $info['smtp_user'] = isset($wpms_settings['smtp']['user']) ? $wpms_settings['smtp']['user'] : null;
                            }
                            if (isset($wpms_settings['mail']['from_email'])) {
                                $info['from_email'] = $wpms_settings['mail']['from_email'];
                            }
                            if (isset($wpms_settings['mail']['from_name'])) {
                                $info['from_name'] = $wpms_settings['mail']['from_name'];
                            }
                        }
                        break;
                        
                    case 'Post SMTP':
                        $postman_options = get_option('postman_options', array());
                        if (!empty($postman_options)) {
                            $info['smtp_host'] = isset($postman_options['hostname']) ? $postman_options['hostname'] : null;
                            $info['smtp_port'] = isset($postman_options['port']) ? $postman_options['port'] : null;
                            $info['smtp_secure'] = isset($postman_options['enc_type']) ? $postman_options['enc_type'] : null;
                            $info['smtp_auth'] = isset($postman_options['auth_type']) ? $postman_options['auth_type'] : null;
                            $info['smtp_user'] = isset($postman_options['basic_auth_username']) ? $postman_options['basic_auth_username'] : null;
                            if (isset($postman_options['sender_email'])) {
                                $info['from_email'] = $postman_options['sender_email'];
                            }
                            if (isset($postman_options['sender_name'])) {
                                $info['from_name'] = $postman_options['sender_name'];
                            }
                        }
                        break;
                        
                    case 'Easy WP SMTP':
                        $easy_smtp = get_option('swpsmtp_options', array());
                        if (!empty($easy_smtp)) {
                            $info['smtp_host'] = isset($easy_smtp['smtp_settings']['host']) ? $easy_smtp['smtp_settings']['host'] : null;
                            $info['smtp_port'] = isset($easy_smtp['smtp_settings']['port']) ? $easy_smtp['smtp_settings']['port'] : null;
                            $info['smtp_secure'] = isset($easy_smtp['smtp_settings']['type_encryption']) ? $easy_smtp['smtp_settings']['type_encryption'] : null;
                            $info['smtp_auth'] = isset($easy_smtp['smtp_settings']['autentication']) ? ($easy_smtp['smtp_settings']['autentication'] === 'yes') : null;
                            $info['smtp_user'] = isset($easy_smtp['smtp_settings']['username']) ? $easy_smtp['smtp_settings']['username'] : null;
                            if (isset($easy_smtp['from_email_field'])) {
                                $info['from_email'] = $easy_smtp['from_email_field'];
                            }
                            if (isset($easy_smtp['from_name_field'])) {
                                $info['from_name'] = $easy_smtp['from_name_field'];
                            }
                        }
                        break;
                        
                    case 'Brevo':
                    case 'Brevo WooCommerce':
                        // Brevo (formerly Sendinblue) plugin settings
                        $brevo_settings = get_option('sib_home_option', array());
                        if (!empty($brevo_settings)) {
                            $info['connection_type'] = 'brevo_api';
                            $info['smtp_host'] = 'smtp-relay.brevo.com';
                            $info['smtp_port'] = 587;
                            $info['smtp_secure'] = 'tls';
                            $info['api_configured'] = !empty($brevo_settings['api_key']) || !empty($brevo_settings['access_key']);
                            if (isset($brevo_settings['from_email'])) {
                                $info['from_email'] = $brevo_settings['from_email'];
                            }
                            if (isset($brevo_settings['from_name'])) {
                                $info['from_name'] = $brevo_settings['from_name'];
                            }
                        }
                        // Also check for newer Brevo option format
                        $brevo_options = get_option('brevo_options', array());
                        if (!empty($brevo_options)) {
                            $info['connection_type'] = 'brevo_api';
                            $info['smtp_host'] = 'smtp-relay.brevo.com';
                            $info['smtp_port'] = 587;
                            $info['smtp_secure'] = 'tls';
                            $info['api_configured'] = !empty($brevo_options['api_key']);
                            if (isset($brevo_options['sender_email'])) {
                                $info['from_email'] = $brevo_options['sender_email'];
                            }
                            if (isset($brevo_options['sender_name'])) {
                                $info['from_name'] = $brevo_options['sender_name'];
                            }
                        }
                        break;
                }
                break;
            }
        }
        
        return array(
            'status' => 'success',
            'action' => 'smtp_info',
            'data' => $info,
            'logs' => 'SMTP configuration info retrieved',
        );
    }
}
