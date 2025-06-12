<?php

class WP_Claude_Code_API {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('wp_ajax_claude_code_test_connection', array($this, 'test_connection'));
    }
    
    public function register_routes() {
        register_rest_route('claude-code/v1', '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_chat_request'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'conversation_id' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        register_rest_route('claude-code/v1', '/site-info', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_site_info'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('claude-code/v1', '/security-status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_security_status'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    public function check_permissions() {
        return current_user_can('edit_posts');
    }
    
    public function handle_chat_request($request) {
        $message = $request->get_param('message');
        $conversation_id = $request->get_param('conversation_id') ?: uniqid('conv_');
        
        // Security logging
        WP_Claude_Code_Security::log_action('chat_request', array(
            'message_length' => strlen($message),
            'conversation_id' => $conversation_id
        ));
        
        // Save user message
        $this->save_message($conversation_id, 'user', $message);
        
        // Process with Claude API
        $claude_api = new WP_Claude_Code_Claude_API();
        $response = $claude_api->send_message($message, $conversation_id);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', $response->get_error_message(), array('status' => 500));
        }
        
        // Save assistant response
        $this->save_message($conversation_id, 'assistant', $response['content'], $response['tools_used'] ?? null);
        
        return rest_ensure_response(array(
            'response' => $response['content'],
            'conversation_id' => $conversation_id,
            'tools_used' => $response['tools_used'] ?? array()
        ));
    }
    
    public function get_site_info() {
        $info = array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'url' => get_site_url(),
                'name' => get_bloginfo('name'),
                'admin_email' => get_option('admin_email'),
                'timezone' => get_option('timezone_string') ?: get_option('gmt_offset'),
                'language' => get_locale()
            ),
            'theme' => array(
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
                'template' => get_template(),
                'stylesheet' => get_stylesheet()
            ),
            'plugins' => array(
                'active' => get_option('active_plugins'),
                'count' => count(get_plugins())
            ),
            'database' => WP_Claude_Code_Database::get_site_info(),
            'system' => array(
                'php_version' => PHP_VERSION,
                'wp_memory_limit' => WP_MEMORY_LIMIT,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
            )
        );
        
        return rest_ensure_response($info);
    }
    
    public function get_security_status() {
        $status = WP_Claude_Code_Security::get_security_status();
        return rest_ensure_response($status);
    }
    
    public function test_connection() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $claude_api = new WP_Claude_Code_Claude_API();
        $result = $claude_api->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    private function save_message($conversation_id, $type, $content, $tools_used = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'conversation_id' => $conversation_id,
                'message_type' => $type,
                'content' => $content,
                'tools_used' => $tools_used ? json_encode($tools_used) : null
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    public function get_conversation_history($conversation_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE conversation_id = %s 
             ORDER BY created_at ASC",
            $conversation_id
        ));
    }
}