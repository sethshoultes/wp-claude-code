<?php

class WP_Claude_Code_Security {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init_security_checks'));
    }
    
    public function init_security_checks() {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));
        
        // Rate limiting for API calls
        add_action('wp_ajax_claude_code_chat', array($this, 'check_rate_limit'), 1);
    }
    
    public function add_security_headers() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'claude-code') !== false) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
        }
    }
    
    public function check_rate_limit() {
        $user_id = get_current_user_id();
        $rate_limit_key = 'claude_code_rate_limit_' . $user_id;
        $requests = get_transient($rate_limit_key);
        
        if ($requests === false) {
            $requests = 0;
        }
        
        $requests++;
        
        // Allow 60 requests per hour
        if ($requests > 60) {
            wp_die('Rate limit exceeded. Please wait before making more requests.');
        }
        
        set_transient($rate_limit_key, $requests, HOUR_IN_SECONDS);
    }
    
    public static function validate_user_permissions($action = 'basic') {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', 'User must be logged in');
        }
        
        switch ($action) {
            case 'file_edit':
                if (!current_user_can('edit_themes') && !current_user_can('edit_plugins')) {
                    return new WP_Error('insufficient_permissions', 'User cannot edit files');
                }
                break;
                
            case 'db_access':
                if (!current_user_can('manage_options')) {
                    return new WP_Error('insufficient_permissions', 'User cannot access database');
                }
                break;
                
            case 'wp_cli':
                if (!current_user_can('manage_options')) {
                    return new WP_Error('insufficient_permissions', 'User cannot execute WP-CLI commands');
                }
                break;
                
            case 'basic':
            default:
                if (!current_user_can('edit_posts')) {
                    return new WP_Error('insufficient_permissions', 'User has insufficient permissions');
                }
                break;
        }
        
        return true;
    }
    
    public static function sanitize_file_path($path) {
        // Remove any directory traversal attempts
        $path = str_replace(array('../', '..\\'), '', $path);
        
        // Remove null bytes
        $path = str_replace("\0", '', $path);
        
        // Normalize slashes
        $path = str_replace('\\', '/', $path);
        
        return $path;
    }
    
    public static function validate_sql_query($query, $allowed_types = array('SELECT')) {
        $query = trim($query);
        $first_word = strtoupper(strtok($query, ' '));
        
        if (!in_array($first_word, $allowed_types)) {
            return new WP_Error('invalid_query_type', 'Query type not allowed: ' . $first_word);
        }
        
        // Check for dangerous SQL patterns
        $dangerous_patterns = array(
            '/\b(DROP|CREATE|ALTER|TRUNCATE|GRANT|REVOKE)\b/i',
            '/\bINTO\s+OUTFILE\b/i',
            '/\bLOAD_FILE\b/i',
            '/\b(INFORMATION_SCHEMA|MYSQL|PERFORMANCE_SCHEMA)\b/i'
        );
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return new WP_Error('dangerous_sql', 'Query contains dangerous SQL patterns');
            }
        }
        
        return true;
    }
    
    public static function log_action($action, $details = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login,
            'action' => $action,
            'details' => $details,
            'ip_address' => self::get_client_ip()
        );
        
        $log_file = WP_CLAUDE_CODE_PLUGIN_PATH . 'logs/security.log';
        
        if (!is_dir(dirname($log_file))) {
            wp_mkdir_p(dirname($log_file));
        }
        
        file_put_contents(
            $log_file, 
            date('Y-m-d H:i:s') . ' - ' . json_encode($log_entry) . "\n", 
            FILE_APPEND | LOCK_EX
        );
    }
    
    public static function encrypt_sensitive_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return $data; // Fallback to plain text if OpenSSL not available
        }
        
        $key = self::get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    public static function decrypt_sensitive_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return $encrypted_data; // Fallback if OpenSSL not available
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = self::get_encryption_key();
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    private static function get_encryption_key() {
        $key = get_option('wp_claude_code_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, false);
            update_option('wp_claude_code_encryption_key', $key);
        }
        
        return $key;
    }
    
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    public static function get_security_status() {
        $status = array(
            'user_permissions' => array(
                'can_edit_files' => current_user_can('edit_themes') || current_user_can('edit_plugins'),
                'can_manage_options' => current_user_can('manage_options'),
                'can_edit_posts' => current_user_can('edit_posts')
            ),
            'system' => array(
                'wp_cli_available' => WP_Claude_Code_WP_CLI_Bridge::is_wp_cli_available(),
                'openssl_available' => function_exists('openssl_encrypt'),
                'file_permissions' => is_writable(WP_CONTENT_DIR)
            ),
            'settings' => array(
                'litellm_configured' => !empty(get_option('wp_claude_code_settings')['litellm_endpoint']),
                'rate_limiting_active' => true,
                'logging_enabled' => true
            )
        );
        
        return $status;
    }
}