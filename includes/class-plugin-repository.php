<?php
/**
 * WordPress.org Plugin Repository Integration Class
 * 
 * This class provides methods to check plugin availability in the WordPress.org repository,
 * fetch plugin details, and provide installation suggestions.
 */

class WP_Claude_Code_Plugin_Repository {
    
    private static $instance = null;
    private $api_url = 'https://api.wordpress.org/plugins/info/1.2/';
    private $cache_group = 'wp_claude_code_plugins';
    private $cache_expiration = 86400; // 24 hours
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize cache if not already created
        wp_cache_add_non_persistent_groups($this->cache_group);
    }
    
    /**
     * Check if a plugin is available in the WordPress.org repository
     *
     * @param string $plugin_name The plugin name or slug to check
     * @return array|WP_Error Result of the check with plugin details or error
     */
    public function check_plugin_availability($plugin_name) {
        // Clean the plugin name for searching
        $search_term = sanitize_text_field($plugin_name);
        
        // Check cache first
        $cache_key = 'plugin_search_' . md5($search_term);
        $cached_result = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Prepare the API request
        $request_args = array(
            'slug' => $search_term,
            'fields' => array(
                'short_description' => true,
                'sections' => false,
                'downloaded' => true,
                'active_installs' => true,
                'rating' => true,
                'ratings' => false,
                'last_updated' => true,
                'homepage' => true,
                'tags' => false,
                'compatibility' => false
            )
        );
        
        // Try exact slug match first
        $response = $this->make_plugin_api_request('plugin_information', $request_args);
        
        // If exact match failed, try a search
        if (is_wp_error($response) || !$response) {
            $request_args = array(
                'per_page' => 5,
                'search' => $search_term,
                'fields' => array(
                    'short_description' => true,
                    'sections' => false,
                    'downloaded' => true,
                    'active_installs' => true,
                    'rating' => true,
                    'ratings' => false,
                    'last_updated' => true,
                    'homepage' => true,
                    'tags' => false,
                    'compatibility' => false
                )
            );
            
            $response = $this->make_plugin_api_request('query_plugins', $request_args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            if (empty($response->plugins)) {
                $result = array(
                    'found' => false,
                    'message' => sprintf('No plugins found matching "%s" in the WordPress.org repository.', $search_term),
                    'suggestions' => $this->get_alternative_suggestions($search_term)
                );
                
                // Cache the result
                wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_expiration);
                return $result;
            }
            
            // Return the search results
            $result = array(
                'found' => true,
                'exact_match' => false,
                'message' => sprintf('Found %d plugins matching "%s" in the WordPress.org repository.', count($response->plugins), $search_term),
                'plugins' => $response->plugins
            );
            
            // Cache the result
            wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_expiration);
            return $result;
        }
        
        // We found an exact match
        $result = array(
            'found' => true,
            'exact_match' => true,
            'message' => sprintf('Found plugin "%s" in the WordPress.org repository.', $response->name),
            'plugin' => $response
        );
        
        // Cache the result
        wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_expiration);
        return $result;
    }
    
    /**
     * Get formatted plugin details for display
     * 
     * @param string $plugin_name The plugin name or slug
     * @return string Formatted plugin details
     */
    public function get_formatted_plugin_details($plugin_name) {
        $result = $this->check_plugin_availability($plugin_name);
        
        if (is_wp_error($result)) {
            return "Error checking plugin: " . $result->get_error_message();
        }
        
        if (!$result['found']) {
            $output = "## Plugin Not Found\n\n";
            $output .= "The plugin \"" . esc_html($plugin_name) . "\" could not be found in the WordPress.org repository.\n\n";
            
            if (!empty($result['suggestions'])) {
                $output .= "### Similar Plugins\n\n";
                foreach ($result['suggestions'] as $suggestion) {
                    $output .= "* **" . esc_html($suggestion['name']) . "** - " . esc_html($suggestion['short_description']) . "\n";
                }
            }
            
            return $output;
        }
        
        if ($result['exact_match']) {
            $plugin = $result['plugin'];
            
            $output = "## " . esc_html($plugin->name) . "\n\n";
            $output .= esc_html($plugin->short_description) . "\n\n";
            
            $output .= "**Version:** " . esc_html($plugin->version) . "\n";
            $output .= "**Author:** " . esc_html(strip_tags($plugin->author)) . "\n";
            $output .= "**Last Updated:** " . esc_html(date('F j, Y', strtotime($plugin->last_updated))) . "\n";
            $output .= "**Active Installs:** " . esc_html(number_format_i18n($plugin->active_installs)) . "+\n";
            $output .= "**Rating:** " . esc_html(number_format($plugin->rating / 20, 1)) . "/5 stars\n\n";
            
            $output .= "### Installation\n\n";
            $output .= "To install this plugin, you can use WP-CLI:\n\n";
            $output .= "`wp plugin install " . esc_html($plugin->slug) . " --activate`\n\n";
            
            $output .= "Or you can install it from the WordPress admin:\n";
            $output .= "1. Go to Plugins > Add New\n";
            $output .= "2. Search for \"" . esc_html($plugin->name) . "\"\n";
            $output .= "3. Click Install Now\n";
            $output .= "4. Activate the plugin\n\n";
            
            $output .= "### Links\n\n";
            $output .= "* [Plugin Page](" . esc_url($plugin->homepage) . ")\n";
            $output .= "* [WordPress.org Plugin Page](https://wordpress.org/plugins/" . esc_html($plugin->slug) . "/)\n";
            
            return $output;
        } else {
            // Display search results
            $plugins = $result['plugins'];
            
            $output = "## Plugin Search Results\n\n";
            $output .= "Found " . count($plugins) . " plugins matching \"" . esc_html($plugin_name) . "\":\n\n";
            
            foreach ($plugins as $plugin) {
                $output .= "### " . esc_html($plugin->name) . "\n\n";
                $output .= esc_html($plugin->short_description) . "\n\n";
                $output .= "**Version:** " . esc_html($plugin->version) . "\n";
                $output .= "**Active Installs:** " . esc_html(number_format_i18n($plugin->active_installs)) . "+\n";
                $output .= "**Rating:** " . esc_html(number_format($plugin->rating / 20, 1)) . "/5 stars\n";
                $output .= "**Installation:** `wp plugin install " . esc_html($plugin->slug) . " --activate`\n\n";
            }
            
            return $output;
        }
    }
    
    /**
     * Get installation instructions for a plugin
     * 
     * @param string $plugin_name The plugin name or slug
     * @return string Installation instructions
     */
    public function get_installation_instructions($plugin_name) {
        $result = $this->check_plugin_availability($plugin_name);
        
        if (is_wp_error($result)) {
            return "Error checking plugin: " . $result->get_error_message();
        }
        
        if (!$result['found']) {
            return "Plugin \"" . esc_html($plugin_name) . "\" could not be found in the WordPress.org repository.";
        }
        
        if ($result['exact_match']) {
            $plugin = $result['plugin'];
            $slug = $plugin->slug;
        } else {
            // Use the first plugin from search results
            $plugin = $result['plugins'][0];
            $slug = $plugin->slug;
        }
        
        $output = "## Installation Instructions for " . esc_html($plugin->name) . "\n\n";
        
        $output .= "### Using WP-CLI\n\n";
        $output .= "```bash\n";
        $output .= "wp plugin install " . esc_html($slug) . " --activate\n";
        $output .= "```\n\n";
        
        $output .= "### From WordPress Admin\n\n";
        $output .= "1. Go to Plugins > Add New\n";
        $output .= "2. Search for \"" . esc_html($plugin->name) . "\"\n";
        $output .= "3. Click Install Now\n";
        $output .= "4. Activate the plugin\n\n";
        
        $output .= "### Manual Installation\n\n";
        $output .= "1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/" . esc_html($slug) . "/)\n";
        $output .= "2. Upload the plugin folder to the `/wp-content/plugins/` directory\n";
        $output .= "3. Activate the plugin through the 'Plugins' menu in WordPress\n";
        
        return $output;
    }
    
    /**
     * Make a request to the WordPress.org Plugin API
     *
     * @param string $action The API action to perform
     * @param array $args Request arguments
     * @return object|WP_Error The API response or WP_Error on failure
     */
    private function make_plugin_api_request($action, $args) {
        $url = $this->api_url . '?' . http_build_query(array(
            'action' => $action,
            'request' => $args
        ));
        
        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (empty($data)) {
            return new WP_Error('invalid_response', 'Invalid response from WordPress.org Plugin API');
        }
        
        return $data;
    }
    
    /**
     * Get alternative suggestions for a plugin
     *
     * @param string $search_term The original search term
     * @return array List of alternative plugins
     */
    private function get_alternative_suggestions($search_term) {
        // Try to get some popular plugins in related categories
        $keywords = $this->extract_keywords($search_term);
        
        if (empty($keywords)) {
            return array();
        }
        
        $request_args = array(
            'per_page' => 3,
            'search' => implode(' ', $keywords),
            'fields' => array(
                'short_description' => true,
                'sections' => false,
                'downloaded' => false,
                'active_installs' => true,
                'rating' => true
            )
        );
        
        $response = $this->make_plugin_api_request('query_plugins', $request_args);
        
        if (is_wp_error($response) || empty($response->plugins)) {
            return array();
        }
        
        return $response->plugins;
    }
    
    /**
     * Extract meaningful keywords from a search term
     *
     * @param string $search_term The search term
     * @return array List of keywords
     */
    private function extract_keywords($search_term) {
        // Convert to lowercase and split by non-alphanumeric characters
        $words = preg_split('/[^\w]+/', strtolower($search_term), -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out common words
        $stop_words = array('the', 'and', 'or', 'for', 'in', 'on', 'at', 'to', 'by', 'with', 'plugin');
        $words = array_diff($words, $stop_words);
        
        return $words;
    }
}