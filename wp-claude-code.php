<?php
/**
 * Plugin Name: WP Claude Code
 * Plugin URI: https://github.com/yourusername/wp-claude-code
 * Description: A Claude Code-style interface for WordPress development and management with AI assistance.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: wp-claude-code
 * Domain Path: /languages
 */

defined('ABSPATH') or die('Direct access not allowed');

define('WP_CLAUDE_CODE_VERSION', '1.0.0');
define('WP_CLAUDE_CODE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_CLAUDE_CODE_PLUGIN_PATH', plugin_dir_path(__FILE__));

class WP_Claude_Code {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load includes
        $this->load_dependencies();
        
        // Initialize components
        if (is_admin()) {
            WP_Claude_Code_Admin::get_instance();
        }
        
        WP_Claude_Code_API::get_instance();
        WP_Claude_Code_Security::get_instance();
    }
    
    private function load_dependencies() {
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-admin.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-api.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-security.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-filesystem.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-database.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-wp-cli-bridge.php';
        require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-claude-api.php';
    }
    
    public function activate() {
        // Create necessary tables or options
        $this->create_tables();
        
        // Set default options using existing LiteLLM proxy from MemberPress AI
        add_option('wp_claude_code_settings', array(
            'litellm_endpoint' => 'https://64.23.251.16.nip.io',
            'api_key' => '3d82afe47512fcb1faba41cc1c9c796d3dbe8624b0a5c62fa68e6d38f0bf6d72',
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 4000,
            'enabled_tools' => array('file_read', 'file_edit', 'wp_cli', 'db_query'),
            'use_memberpress_ai_config' => false // Disabled since we have the correct token
        ));
        
        // Flush rewrite rules for API endpoints
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            conversation_id varchar(100) NOT NULL,
            message_type enum('user', 'assistant') NOT NULL,
            content longtext NOT NULL,
            tools_used longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
WP_Claude_Code::get_instance();