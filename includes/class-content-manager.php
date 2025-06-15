<?php
/**
 * Content Manager Class
 * 
 * This class handles WordPress content management operations through the AI interface.
 */

class WP_Claude_Code_Content_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wp_ajax_claude_code_create_post', array($this, 'handle_create_post'));
        add_action('wp_ajax_claude_code_update_post', array($this, 'handle_update_post'));
        add_action('wp_ajax_claude_code_delete_post', array($this, 'handle_delete_post'));
        add_action('wp_ajax_claude_code_get_posts', array($this, 'handle_get_posts'));
        add_action('wp_ajax_claude_code_get_post', array($this, 'handle_get_post'));
        add_action('wp_ajax_claude_code_bulk_content_action', array($this, 'handle_bulk_action'));
        add_action('wp_ajax_claude_code_get_post_types', array($this, 'handle_get_post_types'));
        add_action('wp_ajax_claude_code_get_taxonomies', array($this, 'handle_get_taxonomies'));
    }
    
    /**
     * Get available tools for Claude API
     */
    public static function get_content_tools() {
        return array(
            'create_post' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'create_post',
                    'description' => 'Create a new WordPress post or page',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'title' => array(
                                'type' => 'string',
                                'description' => 'The post title'
                            ),
                            'content' => array(
                                'type' => 'string',
                                'description' => 'The post content (HTML or plain text)'
                            ),
                            'post_type' => array(
                                'type' => 'string',
                                'description' => 'The post type (post, page, etc.)',
                                'default' => 'post'
                            ),
                            'status' => array(
                                'type' => 'string',
                                'description' => 'Post status (draft, publish, private)',
                                'default' => 'draft'
                            ),
                            'excerpt' => array(
                                'type' => 'string',
                                'description' => 'Post excerpt/summary'
                            ),
                            'categories' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'Category names or IDs'
                            ),
                            'tags' => array(
                                'type' => 'array',
                                'items' => array('type' => 'string'),
                                'description' => 'Tag names'
                            ),
                            'featured_image_url' => array(
                                'type' => 'string',
                                'description' => 'URL of featured image to set'
                            )
                        ),
                        'required' => array('title', 'content')
                    )
                )
            ),
            'update_post' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'update_post',
                    'description' => 'Update an existing WordPress post or page',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'The post ID to update'
                            ),
                            'title' => array(
                                'type' => 'string',
                                'description' => 'The post title'
                            ),
                            'content' => array(
                                'type' => 'string',
                                'description' => 'The post content'
                            ),
                            'status' => array(
                                'type' => 'string',
                                'description' => 'Post status (draft, publish, private)'
                            ),
                            'excerpt' => array(
                                'type' => 'string',
                                'description' => 'Post excerpt/summary'
                            )
                        ),
                        'required' => array('post_id')
                    )
                )
            ),
            'get_posts' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'get_posts',
                    'description' => 'Get a formatted list of WordPress posts, pages, or custom post types with details like title, status, dates, categories, and URLs',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_type' => array(
                                'type' => 'string',
                                'description' => 'Post type to retrieve',
                                'default' => 'post'
                            ),
                            'status' => array(
                                'type' => 'string',
                                'description' => 'Post status filter',
                                'default' => 'any'
                            ),
                            'limit' => array(
                                'type' => 'integer',
                                'description' => 'Number of posts to retrieve',
                                'default' => 10
                            ),
                            'search' => array(
                                'type' => 'string',
                                'description' => 'Search term'
                            )
                        )
                    )
                )
            ),
            'get_post' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'get_post',
                    'description' => 'Get details of a specific post',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'The post ID'
                            )
                        ),
                        'required' => array('post_id')
                    )
                )
            ),
            'delete_post' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'delete_post',
                    'description' => 'Delete a WordPress post (move to trash or permanent)',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'post_id' => array(
                                'type' => 'integer',
                                'description' => 'The post ID to delete'
                            ),
                            'force_delete' => array(
                                'type' => 'boolean',
                                'description' => 'Permanently delete (true) or move to trash (false)',
                                'default' => false
                            )
                        ),
                        'required' => array('post_id')
                    )
                )
            )
        );
    }
    
    /**
     * Execute content management tools
     */
    public static function execute_content_tool($tool_name, $args) {
        $instance = self::get_instance();
        
        switch ($tool_name) {
            case 'create_post':
                return $instance->create_post($args);
            case 'update_post':
                return $instance->update_post($args);
            case 'get_posts':
                return $instance->get_posts($args);
            case 'get_post':
                return $instance->get_post($args);
            case 'delete_post':
                return $instance->delete_post($args);
            default:
                return new WP_Error('invalid_tool', 'Unknown content management tool: ' . $tool_name);
        }
    }
    
    /**
     * Create a new post
     */
    public function create_post($args) {
        $defaults = array(
            'post_type' => 'post',
            'status' => 'draft',
            'title' => '',
            'content' => '',
            'excerpt' => '',
            'categories' => array(),
            'tags' => array(),
            'featured_image_url' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Validate required fields
        if (empty($args['title']) || empty($args['content'])) {
            return new WP_Error('missing_required', 'Title and content are required');
        }
        
        // Prepare post data
        $post_data = array(
            'post_title' => sanitize_text_field($args['title']),
            'post_content' => wp_kses_post($args['content']),
            'post_type' => sanitize_text_field($args['post_type']),
            'post_status' => sanitize_text_field($args['status']),
            'post_author' => get_current_user_id()
        );
        
        if (!empty($args['excerpt'])) {
            $post_data['post_excerpt'] = sanitize_textarea_field($args['excerpt']);
        }
        
        // Insert the post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Handle categories (for posts)
        if (!empty($args['categories']) && $args['post_type'] === 'post') {
            $category_ids = array();
            foreach ($args['categories'] as $cat) {
                if (is_numeric($cat)) {
                    $category_ids[] = (int) $cat;
                } else {
                    $term = get_term_by('name', $cat, 'category');
                    if (!$term) {
                        $term = wp_insert_term($cat, 'category');
                        if (!is_wp_error($term)) {
                            $category_ids[] = $term['term_id'];
                        }
                    } else {
                        $category_ids[] = $term->term_id;
                    }
                }
            }
            if (!empty($category_ids)) {
                wp_set_post_categories($post_id, $category_ids);
            }
        }
        
        // Handle tags (for posts)
        if (!empty($args['tags']) && $args['post_type'] === 'post') {
            wp_set_post_tags($post_id, $args['tags']);
        }
        
        // Handle featured image
        if (!empty($args['featured_image_url'])) {
            $this->set_featured_image_from_url($post_id, $args['featured_image_url']);
        }
        
        $post = get_post($post_id);
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id),
            'status' => $post->post_status,
            'message' => sprintf('Successfully created %s: "%s" (ID: %d)', $args['post_type'], $args['title'], $post_id)
        );
    }
    
    /**
     * Update an existing post
     */
    public function update_post($args) {
        if (empty($args['post_id'])) {
            return new WP_Error('missing_post_id', 'Post ID is required');
        }
        
        $post_id = (int) $args['post_id'];
        $existing_post = get_post($post_id);
        
        if (!$existing_post) {
            return new WP_Error('post_not_found', 'Post not found');
        }
        
        // Prepare update data
        $update_data = array('ID' => $post_id);
        
        if (isset($args['title'])) {
            $update_data['post_title'] = sanitize_text_field($args['title']);
        }
        
        if (isset($args['content'])) {
            $update_data['post_content'] = wp_kses_post($args['content']);
        }
        
        if (isset($args['status'])) {
            $update_data['post_status'] = sanitize_text_field($args['status']);
        }
        
        if (isset($args['excerpt'])) {
            $update_data['post_excerpt'] = sanitize_textarea_field($args['excerpt']);
        }
        
        // Update the post
        $result = wp_update_post($update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $updated_post = get_post($post_id);
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'edit_url' => get_edit_post_link($post_id),
            'status' => $updated_post->post_status,
            'message' => sprintf('Successfully updated %s: "%s" (ID: %d)', $updated_post->post_type, $updated_post->post_title, $post_id)
        );
    }
    
    /**
     * Get posts list
     */
    public function get_posts($args = array()) {
        $defaults = array(
            'post_type' => 'post',
            'status' => 'any',
            'limit' => 10,
            'search' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query_args = array(
            'post_type' => sanitize_text_field($args['post_type']),
            'post_status' => sanitize_text_field($args['status']),
            'posts_per_page' => (int) $args['limit'],
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        if (!empty($args['search'])) {
            $query_args['s'] = sanitize_text_field($args['search']);
        }
        
        $posts = get_posts($query_args);
        
        // Check if this is being called from a tool (formatted output) or AJAX (JSON)
        $from_tool = defined('WP_CLAUDE_CODE_TOOL_EXECUTION') && WP_CLAUDE_CODE_TOOL_EXECUTION;
        
        if ($from_tool) {
            // Return formatted string for tool use
            return $this->format_posts_output($posts, $args);
        } else {
            // Return JSON data for AJAX
            $results = array();
            
            foreach ($posts as $post) {
                $results[] = array(
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'status' => $post->post_status,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'excerpt' => wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30)),
                    'post_type' => $post->post_type,
                    'url' => get_permalink($post->ID),
                    'edit_url' => get_edit_post_link($post->ID)
                );
            }
            
            return array(
                'success' => true,
                'posts' => $results,
                'total_found' => count($results),
                'query' => $args
            );
        }
    }
    
    /**
     * Format posts output for display in chat
     */
    private function format_posts_output($posts, $args) {
        if (empty($posts)) {
            return "# No " . ucfirst($args['post_type']) . "s Found\n\nNo " . $args['post_type'] . "s match your criteria.";
        }
        
        $post_type_label = ucfirst($args['post_type']) . 's';
        $output = "# WordPress $post_type_label\n\n";
        $output .= "**Total Found:** " . count($posts) . " " . strtolower($post_type_label) . "\n";
        
        if (!empty($args['search'])) {
            $output .= "**Search Term:** " . esc_html($args['search']) . "\n";
        }
        
        if ($args['status'] !== 'any') {
            $output .= "**Status Filter:** " . ucfirst($args['status']) . "\n";
        }
        
        $output .= "\n";
        
        foreach ($posts as $post) {
            $status_icon = $this->get_status_icon($post->post_status);
            $output .= "## $status_icon " . esc_html($post->post_title) . "\n";
            $output .= "- **ID:** " . $post->ID . "\n";
            $output .= "- **Status:** " . ucfirst($post->post_status) . "\n";
            $output .= "- **Type:** " . $post->post_type . "\n";
            $output .= "- **Date:** " . $post->post_date . "\n";
            $output .= "- **Modified:** " . $post->post_modified . "\n";
            
            // Add excerpt
            $excerpt = wp_strip_all_tags($post->post_excerpt ?: wp_trim_words($post->post_content, 30));
            if (!empty($excerpt)) {
                $output .= "- **Excerpt:** " . esc_html($excerpt) . "\n";
            }
            
            // Add categories and tags for posts
            if ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if (!empty($categories)) {
                    $cat_names = array_map(function($cat) { return $cat->name; }, $categories);
                    $output .= "- **Categories:** " . implode(', ', $cat_names) . "\n";
                }
                
                $tags = get_the_tags($post->ID);
                if (!empty($tags)) {
                    $tag_names = array_map(function($tag) { return $tag->name; }, $tags);
                    $output .= "- **Tags:** " . implode(', ', $tag_names) . "\n";
                }
            }
            
            $output .= "- **View URL:** " . get_permalink($post->ID) . "\n";
            $output .= "- **Edit URL:** " . get_edit_post_link($post->ID) . "\n";
            $output .= "\n";
        }
        
        // Add summary
        $total_count = wp_count_posts($args['post_type']);
        if (is_object($total_count)) {
            $output .= "---\n\n";
            $output .= "## Summary Statistics\n";
            foreach (get_object_vars($total_count) as $status => $count) {
                if ($count > 0) {
                    $output .= "- **" . ucfirst($status) . ":** $count\n";
                }
            }
        }
        
        return $output;
    }
    
    /**
     * Format single post output for display in chat
     */
    private function format_single_post_output($post, $categories = array(), $tags = array()) {
        $status_icon = $this->get_status_icon($post->post_status);
        $author = get_the_author_meta('display_name', $post->post_author);
        
        $output = "# " . $status_icon . " " . esc_html($post->post_title) . "\n\n";
        $output .= "## Post Details\n";
        $output .= "- **ID:** " . $post->ID . "\n";
        $output .= "- **Status:** " . ucfirst($post->post_status) . "\n";
        $output .= "- **Type:** " . $post->post_type . "\n";
        $output .= "- **Author:** " . $author . "\n";
        $output .= "- **Created:** " . $post->post_date . "\n";
        $output .= "- **Modified:** " . $post->post_modified . "\n";
        
        if (!empty($categories)) {
            $output .= "- **Categories:** " . implode(', ', $categories) . "\n";
        }
        
        if (!empty($tags)) {
            $output .= "- **Tags:** " . implode(', ', $tags) . "\n";
        }
        
        $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
        if ($featured_image) {
            $output .= "- **Featured Image:** " . $featured_image . "\n";
        }
        
        $output .= "- **View URL:** " . get_permalink($post->ID) . "\n";
        $output .= "- **Edit URL:** " . get_edit_post_link($post->ID) . "\n";
        
        if (!empty($post->post_excerpt)) {
            $output .= "\n## Excerpt\n";
            $output .= $post->post_excerpt . "\n";
        }
        
        if (!empty($post->post_content)) {
            $output .= "\n## Content\n";
            // Limit content display to first 1000 characters for readability
            $content = wp_strip_all_tags($post->post_content);
            if (strlen($content) > 1000) {
                $output .= substr($content, 0, 1000) . "...\n\n*[Content truncated - full content available at the edit URL above]*\n";
            } else {
                $output .= $content . "\n";
            }
        }
        
        return $output;
    }
    
    /**
     * Get status icon for posts
     */
    private function get_status_icon($status) {
        switch ($status) {
            case 'publish':
                return '✅';
            case 'draft':
                return '📝';
            case 'private':
                return '🔒';
            case 'trash':
                return '🗑️';
            case 'pending':
                return '⏳';
            case 'future':
                return '⏰';
            default:
                return '📄';
        }
    }
    
    
    /**
     * Get a specific post
     */
    public function get_post($args) {
        if (empty($args['post_id'])) {
            return new WP_Error('missing_post_id', 'Post ID is required');
        }
        
        $post_id = (int) $args['post_id'];
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }
        
        $categories = array();
        $tags = array();
        
        if ($post->post_type === 'post') {
            $post_categories = get_the_category($post_id);
            foreach ($post_categories as $cat) {
                $categories[] = $cat->name;
            }
            
            $post_tags = get_the_tags($post_id);
            if ($post_tags) {
                foreach ($post_tags as $tag) {
                    $tags[] = $tag->name;
                }
            }
        }
        
        // Check if this is being called from a tool (formatted output) or AJAX (JSON)
        $from_tool = defined('WP_CLAUDE_CODE_TOOL_EXECUTION') && WP_CLAUDE_CODE_TOOL_EXECUTION;
        
        if ($from_tool) {
            // Return formatted string for tool use
            return $this->format_single_post_output($post, $categories, $tags);
        } else {
            // Return JSON data for AJAX
            return array(
                'success' => true,
                'post' => array(
                    'ID' => $post->ID,
                    'title' => $post->post_title,
                    'content' => $post->post_content,
                    'excerpt' => $post->post_excerpt,
                    'status' => $post->post_status,
                    'type' => $post->post_type,
                    'date' => $post->post_date,
                    'modified' => $post->post_modified,
                    'author' => get_the_author_meta('display_name', $post->post_author),
                    'url' => get_permalink($post->ID),
                    'edit_url' => get_edit_post_link($post->ID),
                    'categories' => $categories,
                    'tags' => $tags,
                    'featured_image' => get_the_post_thumbnail_url($post->ID, 'full')
                )
            );
        }
    }
    
    /**
     * Delete a post
     */
    public function delete_post($args) {
        if (empty($args['post_id'])) {
            return new WP_Error('missing_post_id', 'Post ID is required');
        }
        
        $post_id = (int) $args['post_id'];
        $force_delete = !empty($args['force_delete']);
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found');
        }
        
        $result = wp_delete_post($post_id, $force_delete);
        
        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete post');
        }
        
        return array(
            'success' => true,
            'message' => sprintf(
                'Successfully %s %s: "%s" (ID: %d)',
                $force_delete ? 'permanently deleted' : 'moved to trash',
                $post->post_type,
                $post->post_title,
                $post_id
            )
        );
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image_from_url($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($image_url, $post_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * Handle AJAX request to create post
     */
    public function handle_create_post() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $args = array(
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'post'),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
            'excerpt' => sanitize_textarea_field($_POST['excerpt'] ?? ''),
            'categories' => array_map('sanitize_text_field', $_POST['categories'] ?? array()),
            'tags' => array_map('sanitize_text_field', $_POST['tags'] ?? array()),
            'featured_image_url' => esc_url_raw($_POST['featured_image_url'] ?? '')
        );
        
        $result = $this->create_post($args);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Handle AJAX request to update post
     */
    public function handle_update_post() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $args = array(
            'post_id' => (int) $_POST['post_id']
        );
        
        if (isset($_POST['title'])) {
            $args['title'] = sanitize_text_field($_POST['title']);
        }
        if (isset($_POST['content'])) {
            $args['content'] = wp_kses_post($_POST['content']);
        }
        if (isset($_POST['status'])) {
            $args['status'] = sanitize_text_field($_POST['status']);
        }
        if (isset($_POST['excerpt'])) {
            $args['excerpt'] = sanitize_textarea_field($_POST['excerpt']);
        }
        
        $result = $this->update_post($args);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Handle AJAX request to get posts
     */
    public function handle_get_posts() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $args = array(
            'post_type' => sanitize_text_field($_POST['post_type'] ?? 'post'),
            'status' => sanitize_text_field($_POST['status'] ?? 'any'),
            'limit' => (int) ($_POST['limit'] ?? 10),
            'search' => sanitize_text_field($_POST['search'] ?? '')
        );
        
        $result = $this->get_posts($args);
        wp_send_json_success($result);
    }
    
    /**
     * Handle AJAX request to get single post
     */
    public function handle_get_post() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $args = array(
            'post_id' => (int) $_POST['post_id']
        );
        
        $result = $this->get_post($args);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Handle AJAX request to delete post
     */
    public function handle_delete_post() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $args = array(
            'post_id' => (int) $_POST['post_id'],
            'force_delete' => !empty($_POST['force_delete'])
        );
        
        $result = $this->delete_post($args);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * Handle bulk content actions
     */
    public function handle_bulk_action() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $action = sanitize_text_field($_POST['action_type']);
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        
        $results = array();
        
        foreach ($post_ids as $post_id) {
            switch ($action) {
                case 'publish':
                    $result = wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
                    $results[] = $result ? "Published post ID $post_id" : "Failed to publish post ID $post_id";
                    break;
                case 'draft':
                    $result = wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                    $results[] = $result ? "Moved post ID $post_id to draft" : "Failed to update post ID $post_id";
                    break;
                case 'trash':
                    $result = wp_trash_post($post_id);
                    $results[] = $result ? "Moved post ID $post_id to trash" : "Failed to trash post ID $post_id";
                    break;
            }
        }
        
        wp_send_json_success(array(
            'message' => 'Bulk action completed',
            'results' => $results
        ));
    }
    
    /**
     * Handle getting post types
     */
    public function handle_get_post_types() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_types = get_post_types(array('public' => true), 'objects');
        $types = array();
        
        foreach ($post_types as $post_type) {
            $types[] = array(
                'name' => $post_type->name,
                'label' => $post_type->label,
                'supports' => get_all_post_type_supports($post_type->name)
            );
        }
        
        wp_send_json_success(array('post_types' => $types));
    }
    
    /**
     * Handle getting taxonomies
     */
    public function handle_get_taxonomies() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $post_type = sanitize_text_field($_POST['post_type'] ?? 'post');
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $tax_data = array();
        
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms(array(
                'taxonomy' => $taxonomy->name,
                'hide_empty' => false
            ));
            
            $tax_data[] = array(
                'name' => $taxonomy->name,
                'label' => $taxonomy->label,
                'terms' => $terms
            );
        }
        
        wp_send_json_success(array('taxonomies' => $tax_data));
    }
}