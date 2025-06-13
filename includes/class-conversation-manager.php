<?php
/**
 * Conversation Manager Class
 * 
 * This class handles conversation history, saved prompts, and conversation management.
 */

class WP_Claude_Code_Conversation_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_claude_code_get_conversations', array($this, 'handle_get_conversations'));
        add_action('wp_ajax_claude_code_get_conversation', array($this, 'handle_get_conversation'));
        add_action('wp_ajax_claude_code_delete_conversation', array($this, 'handle_delete_conversation'));
        add_action('wp_ajax_claude_code_save_prompt', array($this, 'handle_save_prompt'));
        add_action('wp_ajax_claude_code_get_saved_prompts', array($this, 'handle_get_saved_prompts'));
        add_action('wp_ajax_claude_code_delete_prompt', array($this, 'handle_delete_prompt'));
        add_action('wp_ajax_claude_code_rename_conversation', array($this, 'handle_rename_conversation'));
        add_action('wp_ajax_claude_code_increment_prompt_usage', array($this, 'handle_increment_prompt_usage'));
        add_action('wp_ajax_claude_code_get_prompt', array($this, 'handle_get_prompt'));
    }
    
    /**
     * Get conversation list for current user
     */
    public function get_conversations($user_id = null, $limit = 20) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT conversation_id, 
                   MIN(created_at) as started_at,
                   MAX(created_at) as last_activity,
                   COUNT(*) as message_count
            FROM $table_name 
            WHERE user_id = %d 
            GROUP BY conversation_id 
            ORDER BY last_activity DESC 
            LIMIT %d
        ", $user_id, $limit));
        
        // Add conversation titles and preview
        foreach ($results as &$conversation) {
            $title = $this->get_conversation_title($conversation->conversation_id);
            
            // Get first user message for preview
            $preview = $wpdb->get_var($wpdb->prepare("
                SELECT LEFT(content, 100) FROM $table_name 
                WHERE conversation_id = %s AND message_type = 'user' 
                ORDER BY created_at ASC 
                LIMIT 1
            ", $conversation->conversation_id));
            
            $conversation->preview = $preview;
            $conversation->title = $title ?: substr($preview, 0, 50) . '...';
        }
        
        return $results;
    }
    
    /**
     * Get full conversation by ID
     */
    public function get_conversation($conversation_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE conversation_id = %s AND user_id = %d 
            ORDER BY created_at ASC
        ", $conversation_id, $user_id));
    }
    
    /**
     * Delete a conversation
     */
    public function delete_conversation($conversation_id, $user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        $result = $wpdb->delete(
            $table_name,
            array(
                'conversation_id' => $conversation_id,
                'user_id' => $user_id
            ),
            array('%s', '%d')
        );
        
        // Also delete custom title if exists
        delete_option("claude_code_conversation_title_{$conversation_id}");
        
        return $result !== false;
    }
    
    /**
     * Set custom title for conversation
     */
    public function set_conversation_title($conversation_id, $title) {
        return update_option("claude_code_conversation_title_{$conversation_id}", sanitize_text_field($title));
    }
    
    /**
     * Get conversation title
     */
    public function get_conversation_title($conversation_id) {
        return get_option("claude_code_conversation_title_{$conversation_id}", '');
    }
    
    /**
     * Save a prompt template
     */
    public function save_prompt($title, $content, $category = 'general', $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $saved_prompts = get_option('claude_code_saved_prompts', array());
        
        $prompt_id = uniqid('prompt_');
        $saved_prompts[$prompt_id] = array(
            'title' => sanitize_text_field($title),
            'content' => sanitize_textarea_field($content),
            'category' => sanitize_text_field($category),
            'user_id' => $user_id,
            'created_at' => current_time('mysql'),
            'usage_count' => 0
        );
        
        update_option('claude_code_saved_prompts', $saved_prompts);
        
        return $prompt_id;
    }
    
    /**
     * Get saved prompts for user
     */
    public function get_saved_prompts($user_id = null, $category = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $saved_prompts = get_option('claude_code_saved_prompts', array());
        $user_prompts = array();
        
        foreach ($saved_prompts as $id => $prompt) {
            if ($prompt['user_id'] == $user_id) {
                if (!$category || $prompt['category'] == $category) {
                    $prompt['id'] = $id;
                    $user_prompts[] = $prompt;
                }
            }
        }
        
        // Sort by usage count and creation date
        usort($user_prompts, function($a, $b) {
            if ($a['usage_count'] == $b['usage_count']) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            }
            return $b['usage_count'] - $a['usage_count'];
        });
        
        return $user_prompts;
    }
    
    /**
     * Delete a saved prompt
     */
    public function delete_prompt($prompt_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $saved_prompts = get_option('claude_code_saved_prompts', array());
        
        if (isset($saved_prompts[$prompt_id]) && $saved_prompts[$prompt_id]['user_id'] == $user_id) {
            unset($saved_prompts[$prompt_id]);
            update_option('claude_code_saved_prompts', $saved_prompts);
            return true;
        }
        
        return false;
    }
    
    /**
     * Increment usage count for a prompt
     */
    public function increment_prompt_usage($prompt_id) {
        $saved_prompts = get_option('claude_code_saved_prompts', array());
        
        if (isset($saved_prompts[$prompt_id])) {
            $saved_prompts[$prompt_id]['usage_count']++;
            update_option('claude_code_saved_prompts', $saved_prompts);
        }
    }
    
    /**
     * Get prompt categories
     */
    public function get_prompt_categories($user_id = null) {
        $prompts = $this->get_saved_prompts($user_id);
        $categories = array('general');
        
        foreach ($prompts as $prompt) {
            if (!in_array($prompt['category'], $categories)) {
                $categories[] = $prompt['category'];
            }
        }
        
        return $categories;
    }
    
    /**
     * Handle AJAX request to get conversations
     */
    public function handle_get_conversations() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $conversations = $this->get_conversations();
        
        wp_send_json_success(array(
            'conversations' => $conversations
        ));
    }
    
    /**
     * Handle AJAX request to get a specific conversation
     */
    public function handle_get_conversation() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $conversation_id = sanitize_text_field($_POST['conversation_id']);
        $messages = $this->get_conversation($conversation_id);
        
        wp_send_json_success(array(
            'messages' => $messages,
            'conversation_id' => $conversation_id
        ));
    }
    
    /**
     * Handle AJAX request to delete conversation
     */
    public function handle_delete_conversation() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $conversation_id = sanitize_text_field($_POST['conversation_id']);
        $success = $this->delete_conversation($conversation_id);
        
        if ($success) {
            wp_send_json_success(array('message' => 'Conversation deleted'));
        } else {
            wp_send_json_error('Failed to delete conversation');
        }
    }
    
    /**
     * Handle AJAX request to rename conversation
     */
    public function handle_rename_conversation() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $conversation_id = sanitize_text_field($_POST['conversation_id']);
        $title = sanitize_text_field($_POST['title']);
        
        $this->set_conversation_title($conversation_id, $title);
        
        wp_send_json_success(array('message' => 'Conversation renamed'));
    }
    
    /**
     * Handle AJAX request to save prompt
     */
    public function handle_save_prompt() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $title = sanitize_text_field($_POST['title']);
        $content = sanitize_textarea_field($_POST['content']);
        $category = sanitize_text_field($_POST['category'] ?? 'general');
        
        $prompt_id = $this->save_prompt($title, $content, $category);
        
        wp_send_json_success(array(
            'message' => 'Prompt saved',
            'prompt_id' => $prompt_id
        ));
    }
    
    /**
     * Handle AJAX request to get saved prompts
     */
    public function handle_get_saved_prompts() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $category = sanitize_text_field($_POST['category'] ?? '');
        $prompts = $this->get_saved_prompts(null, $category ?: null);
        $categories = $this->get_prompt_categories();
        
        wp_send_json_success(array(
            'prompts' => $prompts,
            'categories' => $categories
        ));
    }
    
    /**
     * Handle AJAX request to delete prompt
     */
    public function handle_delete_prompt() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $prompt_id = sanitize_text_field($_POST['prompt_id']);
        $success = $this->delete_prompt($prompt_id);
        
        if ($success) {
            wp_send_json_success(array('message' => 'Prompt deleted'));
        } else {
            wp_send_json_error('Failed to delete prompt');
        }
    }
    
    /**
     * Handle AJAX request to increment prompt usage
     */
    public function handle_increment_prompt_usage() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $prompt_id = sanitize_text_field($_POST['prompt_id']);
        $this->increment_prompt_usage($prompt_id);
        
        wp_send_json_success(array('message' => 'Usage incremented'));
    }
    
    /**
     * Handle AJAX request to get a single prompt
     */
    public function handle_get_prompt() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $prompt_id = sanitize_text_field($_POST['prompt_id']);
        $user_id = get_current_user_id();
        
        $saved_prompts = get_option('claude_code_saved_prompts', array());
        
        if (isset($saved_prompts[$prompt_id]) && $saved_prompts[$prompt_id]['user_id'] == $user_id) {
            wp_send_json_success(array(
                'content' => $saved_prompts[$prompt_id]['content'],
                'title' => $saved_prompts[$prompt_id]['title']
            ));
        } else {
            wp_send_json_error('Prompt not found');
        }
    }
}