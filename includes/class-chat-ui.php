<?php
/**
 * Modern Chat UI Class
 * 
 * This class provides a modern chat interface for WP Claude Code with markdown rendering,
 * syntax highlighting, and WhatsApp-style message bubbles.
 */

class WP_Claude_Code_Chat_UI {
    
    private static $instance = null;
    private $is_enabled = true;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if the chat UI module is enabled
        $settings = get_option('wp_claude_code_settings', array());
        $this->is_enabled = isset($settings['chat_ui_enabled']) ? (bool)$settings['chat_ui_enabled'] : true;
        
        if ($this->is_enabled) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('wp_ajax_claude_code_render_markdown', array($this, 'handle_markdown_rendering'));
            add_filter('claude_code_settings_fields', array($this, 'add_settings_fields'));
        }
    }
    
    /**
     * Enqueue scripts and styles for the modern chat UI
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'claude-code') === false) {
            return;
        }
        
        // Marked.js for Markdown parsing
        wp_enqueue_script(
            'marked-js',
            'https://cdn.jsdelivr.net/npm/marked@4.3.0/marked.min.js',
            array(),
            '4.3.0',
            true
        );
        
        // Highlight.js for syntax highlighting
        wp_enqueue_script(
            'highlight-js',
            'https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/lib/highlight.min.js',
            array(),
            '11.7.0',
            true
        );
        
        wp_enqueue_style(
            'highlight-js-style',
            'https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/styles/github-dark.min.css',
            array(),
            '11.7.0'
        );
        
        // Custom chat UI scripts and styles
        wp_enqueue_script(
            'claude-code-chat-ui',
            WP_CLAUDE_CODE_PLUGIN_URL . 'assets/js/chat-ui.js',
            array('jquery', 'marked-js', 'highlight-js'),
            WP_CLAUDE_CODE_VERSION,
            true
        );
        
        wp_enqueue_style(
            'claude-code-chat-ui',
            WP_CLAUDE_CODE_PLUGIN_URL . 'assets/css/chat-ui.css',
            array(),
            WP_CLAUDE_CODE_VERSION
        );
        
        // Localize script with settings
        wp_localize_script('claude-code-chat-ui', 'claudeCodeChatUI', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('claude_code_chat_ui_nonce'),
            'isEnabled' => $this->is_enabled,
            'defaultMarkdownRenderer' => 'client' // 'client' or 'server'
        ));
    }
    
    /**
     * Add settings fields for the Chat UI module
     */
    public function add_settings_fields($fields) {
        $fields['chat_ui'] = array(
            'title' => 'Modern Chat UI',
            'fields' => array(
                'chat_ui_enabled' => array(
                    'label' => 'Enable Modern Chat UI',
                    'type' => 'checkbox',
                    'default' => true,
                    'description' => 'Provides markdown rendering, syntax highlighting, and WhatsApp-style bubbles'
                ),
                'chat_ui_renderer' => array(
                    'label' => 'Markdown Rendering',
                    'type' => 'select',
                    'options' => array(
                        'client' => 'Client-side (faster)',
                        'server' => 'Server-side (more secure)'
                    ),
                    'default' => 'client',
                    'description' => 'Choose where to render markdown content'
                )
            )
        );
        
        return $fields;
    }
    
    /**
     * Handle server-side markdown rendering
     */
    public function handle_markdown_rendering() {
        check_ajax_referer('claude_code_chat_ui_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $markdown = isset($_POST['markdown']) ? $_POST['markdown'] : '';
        
        if (empty($markdown)) {
            wp_send_json_error('No markdown content provided');
        }
        
        // Use a server-side markdown parser
        // For simplicity, we'll use a basic implementation here
        // In a production environment, consider using a proper parser library
        $html = $this->parse_markdown($markdown);
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * Basic server-side markdown parser
     * This is a simplified implementation and should be replaced with a proper library in production
     */
    private function parse_markdown($markdown) {
        // Convert headers
        $markdown = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $markdown);
        $markdown = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $markdown);
        $markdown = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $markdown);
        
        // Convert lists
        $markdown = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $markdown);
        $markdown = preg_replace('/^\- (.*?)$/m', '<li>$1</li>', $markdown);
        $markdown = str_replace("<li>", "<ul><li>", $markdown);
        $markdown = str_replace("</li>\n", "</li></ul>\n", $markdown);
        
        // Convert code blocks
        $markdown = preg_replace('/```(.*?)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $markdown);
        
        // Convert inline code
        $markdown = preg_replace('/`(.*?)`/', '<code>$1</code>', $markdown);
        
        // Convert bold
        $markdown = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $markdown);
        
        // Convert italic
        $markdown = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $markdown);
        
        // Convert links
        $markdown = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $markdown);
        
        // Convert line breaks
        $markdown = str_replace("\n", "<br>", $markdown);
        
        return $markdown;
    }
    
    /**
     * Check if the chat UI module is enabled
     */
    public function is_enabled() {
        return $this->is_enabled;
    }
}