<?php

class WP_Claude_Code_Claude_API {
    
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('wp_claude_code_settings', array());
        
        // If configured to use MemberPress AI settings and no manual API key is set, try to get API key from there
        if (!empty($this->settings['use_memberpress_ai_config']) && empty($this->settings['api_key'])) {
            $this->load_memberpress_ai_config();
        }
    }
    
    private function load_memberpress_ai_config() {
        error_log('WP Claude Code: Attempting to load MemberPress AI configuration');
        
        // Check if MemberPress AI Assistant is active and has settings
        if (class_exists('\MemberpressAiAssistant\Admin\MPAIKeyManager')) {
            error_log('WP Claude Code: MemberPress AI KeyManager class found');
            try {
                $key_manager = new \MemberpressAiAssistant\Admin\MPAIKeyManager();
                $api_key = $key_manager->get_api_key('anthropic');
                
                if ($api_key) {
                    $this->settings['api_key'] = $api_key;
                    error_log('WP Claude Code: Successfully loaded API key from MemberPress AI KeyManager');
                } else {
                    error_log('WP Claude Code: No API key returned from MemberPress AI KeyManager');
                }
            } catch (Exception $e) {
                error_log('WP Claude Code: Error loading MemberPress AI config: ' . $e->getMessage());
            }
        } else {
            error_log('WP Claude Code: MemberPress AI KeyManager class not found, trying direct database access');
        }
        
        // Always try fallback: get from MemberPress AI database options directly
        $mpai_settings = get_option('mpai_settings', array());
        error_log('WP Claude Code: MemberPress AI settings found: ' . (!empty($mpai_settings) ? 'Yes' : 'No'));
        
        if (!empty($mpai_settings)) {
            error_log('WP Claude Code: Available keys in mpai_settings: ' . implode(', ', array_keys($mpai_settings)));
            
            if (!empty($mpai_settings['anthropic_api_key'])) {
                $this->settings['api_key'] = $mpai_settings['anthropic_api_key'];
                error_log('WP Claude Code: Loaded API key from MemberPress AI database options');
            } else {
                error_log('WP Claude Code: No anthropic_api_key found in mpai_settings');
            }
        }
        
        // Final check - log what we have
        error_log('WP Claude Code: Final API key status: ' . (!empty($this->settings['api_key']) ? 'Available' : 'Not available'));
    }
    
    public function send_message($message, $conversation_id = '') {
        $endpoint = $this->settings['litellm_endpoint'] ?? '';
        
        if (empty($endpoint)) {
            return new WP_Error('no_endpoint', 'LiteLLM endpoint not configured');
        }
        
        $conversation_history = $this->get_conversation_history($conversation_id);
        $system_prompt = $this->get_system_prompt();
        $tools = $this->get_available_tools();
        
        $messages = array();
        
        // Add system message
        $messages[] = array(
            'role' => 'system',
            'content' => $system_prompt
        );
        
        // Add conversation history
        foreach ($conversation_history as $msg) {
            $messages[] = array(
                'role' => $msg->message_type === 'user' ? 'user' : 'assistant',
                'content' => $msg->content
            );
        }
        
        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        $request_data = array(
            'model' => $this->settings['model'] ?? 'claude-3-sonnet',
            'messages' => $messages,
            'max_tokens' => intval($this->settings['max_tokens'] ?? 4000),
            'tools' => $tools
        );
        
        $response = $this->make_api_request($endpoint, $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->process_response($response);
    }
    
    private function get_system_prompt() {
        global $wp_version;
        $current_theme = wp_get_theme();
        $site_url = get_site_url();
        
        return "You are Claude Code, an AI assistant specialized in WordPress development and management. You are running as a plugin within a WordPress site.

CURRENT ENVIRONMENT:
- WordPress Version: {$wp_version}
- Site URL: {$site_url}
- Active Theme: {$current_theme->get('Name')} v{$current_theme->get('Version')}
- WordPress Path: " . ABSPATH . "
- Plugin Path: " . WP_CLAUDE_CODE_PLUGIN_PATH . "

AVAILABLE TOOLS:
- wp_file_read: Read WordPress files (themes, plugins, config)
- wp_file_edit: Edit WordPress files with backup and validation
- wp_db_query: Execute safe database queries
- wp_cli_exec: Run WP-CLI commands
- wp_content_create: Create posts, pages, and custom content
- wp_staging_create: Create staging environments
- wp_plugin_check: Check WordPress.org plugin repository for plugin availability and details

CAPABILITIES:
1. Theme and plugin development assistance
2. Database operations and content management
3. Site administration and optimization
4. Code analysis and debugging
5. Security best practices guidance
6. Performance optimization recommendations

SECURITY GUIDELINES:
- Never edit wp-config.php directly
- Always validate and sanitize user inputs
- Use WordPress APIs when possible
- Create backups before major changes
- Respect user permissions and capabilities

INSTRUCTIONS:
- Be concise and practical in your responses
- Provide working code examples when helpful
- Suggest WordPress best practices
- Always explain what you're doing and why
- Ask for confirmation before making significant changes

How can I help you with your WordPress development today?";
    }
    
    private function get_available_tools() {
        $enabled_tools = $this->settings['enabled_tools'] ?? array();
        $tools = array();

        if (in_array('file_read', $enabled_tools)) {
            $tools[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => 'wp_file_read',
                    'description' => 'Read a WordPress file (theme, plugin, or core file)',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'file_path' => array(
                                'type' => 'string',
                                'description' => 'Relative path from WordPress root or absolute path'
                            )
                        ),
                        'required' => array('file_path')
                    )
                )
            );
        }

        // Always enable the plugin repository check
        if (in_array('plugin_repository', $enabled_tools) || true) {
            $tools[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => 'wp_plugin_check',
                    'description' => 'Check if a plugin is available in the WordPress.org repository and get details about it',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'plugin_name' => array(
                                'type' => 'string',
                                'description' => 'The name or slug of the plugin to check'
                            ),
                            'detail_level' => array(
                                'type' => 'string',
                                'enum' => array('basic', 'detailed', 'installation'),
                                'description' => 'The level of detail to return (basic, detailed, or installation instructions)',
                                'default' => 'basic'
                            )
                        ),
                        'required' => array('plugin_name')
                    )
                )
            );
        }
        
        if (in_array('file_edit', $enabled_tools)) {
            $tools[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => 'wp_file_edit',
                    'description' => 'Edit a WordPress file with backup and validation',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'file_path' => array(
                                'type' => 'string',
                                'description' => 'Path to the file to edit'
                            ),
                            'content' => array(
                                'type' => 'string',
                                'description' => 'New file content'
                            ),
                            'backup' => array(
                                'type' => 'boolean',
                                'description' => 'Whether to create a backup (default: true)'
                            )
                        ),
                        'required' => array('file_path', 'content')
                    )
                )
            );
        }
        
        if (in_array('wp_cli', $enabled_tools)) {
            $tools[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => 'wp_cli_exec',
                    'description' => 'Execute a WP-CLI command',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'command' => array(
                                'type' => 'string',
                                'description' => 'WP-CLI command to execute (without "wp" prefix)'
                            )
                        ),
                        'required' => array('command')
                    )
                )
            );
        }
        
        if (in_array('db_query', $enabled_tools)) {
            $tools[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => 'wp_db_query',
                    'description' => 'Execute a safe database query',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'query' => array(
                                'type' => 'string',
                                'description' => 'SQL query to execute'
                            ),
                            'query_type' => array(
                                'type' => 'string',
                                'enum' => array('SELECT', 'UPDATE', 'INSERT', 'DELETE'),
                                'description' => 'Type of query for validation'
                            )
                        ),
                        'required' => array('query', 'query_type')
                    )
                )
            );
        }
        
        // Add specific tools for better selection
        $tools[] = array(
            'type' => 'function',
            'function' => array(
                'name' => 'wp_theme_info',
                'description' => 'Get detailed information about the active WordPress theme including name, version, author, features, and file structure',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'include_files' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to include theme file listing (default: true)'
                        )
                    ),
                    'required' => array()
                )
            )
        );
        
        $tools[] = array(
            'type' => 'function',
            'function' => array(
                'name' => 'wp_database_status',
                'description' => 'Check WordPress database status, statistics, table information, and performance metrics',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'include_tables' => array(
                            'type' => 'boolean',
                            'description' => 'Whether to include detailed table information (default: true)'
                        )
                    ),
                    'required' => array()
                )
            )
        );
        
        $tools[] = array(
            'type' => 'function',
            'function' => array(
                'name' => 'wp_site_info',
                'description' => 'Get comprehensive WordPress site information including configuration, theme, plugins, database stats, and server environment - use only when requesting complete site overview',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'info_type' => array(
                            'type' => 'string',
                            'enum' => array('all', 'plugins', 'server', 'config'),
                            'description' => 'Type of information to retrieve (default: all)'
                        )
                    ),
                    'required' => array()
                )
            )
        );
        
        // Always add content management tool
        $tools[] = array(
            'type' => 'function',
            'function' => array(
                'name' => 'wp_content_list',
                'description' => 'List WordPress content (posts, pages, custom post types)',
                'parameters' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_type' => array(
                            'type' => 'string',
                            'description' => 'Type of content to list (post, page, any, or custom post type)',
                            'default' => 'post'
                        ),
                        'status' => array(
                            'type' => 'string',
                            'enum' => array('publish', 'draft', 'private', 'trash', 'any'),
                            'description' => 'Post status to filter by',
                            'default' => 'any'
                        ),
                        'limit' => array(
                            'type' => 'integer',
                            'description' => 'Number of posts to return (max 50)',
                            'default' => 20
                        )
                    ),
                    'required' => array()
                )
            )
        );
        
        return $tools;
    }
    
    private function get_conversation_history($conversation_id) {
        if (empty($conversation_id)) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_code_conversations';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT message_type, content FROM $table_name 
             WHERE conversation_id = %s 
             ORDER BY created_at ASC 
             LIMIT 20",
            $conversation_id
        ));
    }
    
    private function make_api_request($endpoint, $data) {
        $url = rtrim($endpoint, '/') . '/v1/chat/completions';
        
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        // Debug logging for API key
        error_log('WP Claude Code: API Key available: ' . (!empty($this->settings['api_key']) ? 'Yes' : 'No'));
        error_log('WP Claude Code: Endpoint: ' . $url);
        
        if (!empty($this->settings['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $this->settings['api_key'];
            error_log('WP Claude Code: Added Authorization header');
        } else {
            error_log('WP Claude Code: No API key found in settings');
            return new WP_Error('no_api_key', 'No API key configured');
        }
        
        $args = array(
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 60,
            'method' => 'POST'
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            return new WP_Error('api_error', "API request failed with status $status_code: $body");
        }
        
        $decoded = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON response from API');
        }
        
        return $decoded;
    }
    
    private function process_response($response) {
        if (!isset($response['choices'][0]['message'])) {
            return new WP_Error('invalid_response', 'Invalid response format from API');
        }
        
        $message = $response['choices'][0]['message'];
        $content = $message['content'] ?? '';
        $tools_used = array();
        
        // Process tool calls if present
        if (isset($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tool_call) {
                $function_name = $tool_call['function']['name'];
                $arguments = json_decode($tool_call['function']['arguments'], true);
                
                $tool_result = $this->execute_tool($function_name, $arguments);
                $tools_used[] = $function_name;
                
                if (!is_wp_error($tool_result)) {
                    $content .= "\n\n" . $tool_result;
                } else {
                    $content .= "\n\nTool execution error: " . $tool_result->get_error_message();
                }
            }
        }
        
        return array(
            'content' => $content,
            'tools_used' => $tools_used
        );
    }
    
    private function execute_tool($function_name, $arguments) {
        error_log("WP Claude Code: Executing tool: $function_name with arguments: " . json_encode($arguments));

        switch ($function_name) {
            case 'wp_file_read':
                $result = WP_Claude_Code_Filesystem::read_file($arguments['file_path']);
                break;

            case 'wp_file_edit':
                $result = WP_Claude_Code_Filesystem::edit_file(
                    $arguments['file_path'],
                    $arguments['content'],
                    $arguments['backup'] ?? true
                );
                break;

            case 'wp_cli_exec':
                $result = WP_Claude_Code_WP_CLI_Bridge::execute($arguments['command']);
                error_log("WP Claude Code: WP-CLI result: " . json_encode($result));
                break;

            case 'wp_db_query':
                $result = WP_Claude_Code_Database::execute_query(
                    $arguments['query'],
                    $arguments['query_type']
                );
                break;

            case 'wp_plugin_check':
                // Load the plugin repository class
                if (!class_exists('WP_Claude_Code_Plugin_Repository')) {
                    require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-plugin-repository.php';
                }

                $plugin_name = $arguments['plugin_name'];
                $detail_level = $arguments['detail_level'] ?? 'basic';

                $repository = WP_Claude_Code_Plugin_Repository::get_instance();

                if ($detail_level === 'basic') {
                    $result = $repository->check_plugin_availability($plugin_name);
                    if (!is_wp_error($result)) {
                        $result = "Plugin check for \"{$plugin_name}\":\n\n" . $result['message'];
                    }
                } elseif ($detail_level === 'detailed') {
                    $result = $repository->get_formatted_plugin_details($plugin_name);
                } elseif ($detail_level === 'installation') {
                    $result = $repository->get_installation_instructions($plugin_name);
                }
                break;

            case 'wp_site_info':
                $info_type = $arguments['info_type'] ?? 'all';
                $result = $this->get_site_info_native($info_type);
                break;

            case 'wp_content_list':
                $post_type = $arguments['post_type'] ?? 'post';
                $status = $arguments['status'] ?? 'any';
                $limit = min(intval($arguments['limit'] ?? 20), 50);
                $result = $this->get_content_list($post_type, $status, $limit);
                break;

            case 'wp_theme_info':
                $include_files = $arguments['include_files'] ?? true;
                $result = $this->get_theme_info($include_files);
                break;

            case 'wp_database_status':
                $include_tables = $arguments['include_tables'] ?? true;
                $result = $this->get_database_status($include_tables);
                break;

            default:
                return new WP_Error('unknown_tool', "Unknown tool: $function_name");
        }
        
        error_log("WP Claude Code: Tool execution result: " . json_encode($result));
        return $result;
    }
    
    private function get_site_info_native($info_type = 'all') {
        global $wp_version, $wpdb;
        
        // Get theme info
        $current_theme = wp_get_theme();
        
        // Get plugin info
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        
        // Enhanced plugin information
        $plugin_details = array();
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins);
            $plugin_details[] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'status' => $is_active ? 'active' : 'inactive',
                'file' => $plugin_file,
                'network' => $plugin_data['Network'] ?? false,
                'requires_wp' => $plugin_data['RequiresWP'] ?? 'Unknown',
                'requires_php' => $plugin_data['RequiresPHP'] ?? 'Unknown'
            );
        }
        
        // Get database info
        $db_info = WP_Claude_Code_Database::get_site_info();
        
        $site_info = array(
            'wordpress' => array(
                'version' => $wp_version,
                'url' => get_site_url(),
                'admin_url' => admin_url(),
                'name' => get_bloginfo('name'),
                'description' => get_bloginfo('description'),
                'admin_email' => get_option('admin_email'),
                'timezone' => get_option('timezone_string') ?: get_option('gmt_offset'),
                'language' => get_locale(),
                'multisite' => is_multisite(),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
                'memory_limit' => WP_MEMORY_LIMIT,
            ),
            'theme' => array(
                'name' => $current_theme->get('Name'),
                'version' => $current_theme->get('Version'),
                'description' => $current_theme->get('Description'),
                'author' => $current_theme->get('Author'),
                'template' => get_template(),
                'stylesheet' => get_stylesheet(),
                'parent_theme' => $current_theme->parent() ? $current_theme->parent()->get('Name') : null,
            ),
            'plugins' => array(
                'total_count' => count($all_plugins),
                'active_count' => count($active_plugins),
                'active_plugins' => array_map(function($plugin_file) use ($all_plugins) {
                    return isset($all_plugins[$plugin_file]) ? $all_plugins[$plugin_file]['Name'] : basename($plugin_file);
                }, $active_plugins),
            ),
            'database' => $db_info,
            'server' => array(
                'php_version' => PHP_VERSION,
                'mysql_version' => $wpdb->db_version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'wordpress_path' => ABSPATH,
                'wp_content_path' => WP_CONTENT_DIR,
                'uploads_dir' => wp_upload_dir()['basedir'] ?? 'Unknown',
            ),
            'configuration' => array(
                'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
                'wp_debug_log' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
                'wp_debug_display' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
                'script_debug' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false,
                'wp_cache' => defined('WP_CACHE') ? WP_CACHE : false,
                'force_ssl_admin' => defined('FORCE_SSL_ADMIN') ? FORCE_SSL_ADMIN : false,
                'automatic_updater_disabled' => defined('AUTOMATIC_UPDATER_DISABLED') ? AUTOMATIC_UPDATER_DISABLED : false,
            ),
            'plugin_details' => $plugin_details
        );
        
        // Return specific information based on request type
        if ($info_type === 'plugins') {
            $output = "# WordPress Plugins\n\n";
            $output .= "**Total Plugins:** " . count($all_plugins) . "\n";
            $output .= "**Active Plugins:** " . count($active_plugins) . "\n\n";
            
            // Sort plugins by status (active first)
            usort($plugin_details, function($a, $b) {
                if ($a['status'] === $b['status']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $a['status'] === 'active' ? -1 : 1;
            });
            
            foreach ($plugin_details as $plugin) {
                $status_icon = $plugin['status'] === 'active' ? '✅' : '⚪';
                $output .= "## {$status_icon} {$plugin['name']}\n";
                $output .= "- **Status:** " . ucfirst($plugin['status']) . "\n";
                $output .= "- **Version:** {$plugin['version']}\n";
                $output .= "- **Author:** {$plugin['author']}\n";
                if (!empty($plugin['description'])) {
                    $output .= "- **Description:** " . substr($plugin['description'], 0, 100) . (strlen($plugin['description']) > 100 ? '...' : '') . "\n";
                }
                $output .= "- **File:** {$plugin['file']}\n";
                if ($plugin['requires_wp'] !== 'Unknown') {
                    $output .= "- **Requires WordPress:** {$plugin['requires_wp']}\n";
                }
                if ($plugin['requires_php'] !== 'Unknown') {
                    $output .= "- **Requires PHP:** {$plugin['requires_php']}\n";
                }
                $output .= "\n";
            }
            
            return $output;
        }
        
        // Return full site information for 'all' or other types
        return "# WordPress Site Information\n\n" . 
               "## WordPress Core\n" .
               "- **Version:** {$site_info['wordpress']['version']}\n" .
               "- **Site URL:** {$site_info['wordpress']['url']}\n" .
               "- **Site Name:** {$site_info['wordpress']['name']}\n" .
               "- **Admin Email:** {$site_info['wordpress']['admin_email']}\n" .
               "- **Language:** {$site_info['wordpress']['language']}\n" .
               "- **Timezone:** {$site_info['wordpress']['timezone']}\n" .
               "- **Multisite:** " . ($site_info['wordpress']['multisite'] ? 'Yes' : 'No') . "\n" .
               "- **Debug Mode:** " . ($site_info['wordpress']['debug_mode'] ? 'Enabled' : 'Disabled') . "\n" .
               "- **Memory Limit:** {$site_info['wordpress']['memory_limit']}\n\n" .
               
               "## Active Theme\n" .
               "- **Name:** {$site_info['theme']['name']}\n" .
               "- **Version:** {$site_info['theme']['version']}\n" .
               "- **Author:** {$site_info['theme']['author']}\n" .
               "- **Template:** {$site_info['theme']['template']}\n" .
               ($site_info['theme']['parent_theme'] ? "- **Parent Theme:** {$site_info['theme']['parent_theme']}\n" : "") . "\n" .
               
               "## Plugins\n" .
               "- **Total Plugins:** {$site_info['plugins']['total_count']}\n" .
               "- **Active Plugins:** {$site_info['plugins']['active_count']}\n" .
               "- **Active Plugin List:**\n" .
               implode("\n", array_map(function($plugin) { return "  - $plugin"; }, $site_info['plugins']['active_plugins'])) . "\n\n" .
               
               "## Database\n" .
               "- **Name:** {$site_info['database']['database']['name']}\n" .
               "- **Host:** {$site_info['database']['database']['host']}\n" .
               "- **Prefix:** {$site_info['database']['database']['prefix']}\n" .
               "- **Charset:** {$site_info['database']['database']['charset']}\n" .
               "- **Posts:** {$site_info['database']['tables']['posts']}\n" .
               "- **Users:** {$site_info['database']['tables']['users']}\n" .
               "- **Comments:** {$site_info['database']['tables']['comments']}\n\n" .
               
               "## Server Environment\n" .
               "- **PHP Version:** {$site_info['server']['php_version']}\n" .
               "- **MySQL Version:** {$site_info['server']['mysql_version']}\n" .
               "- **Server Software:** {$site_info['server']['server_software']}\n" .
               "- **WordPress Path:** {$site_info['server']['wordpress_path']}\n" .
               "- **WP Content Path:** {$site_info['server']['wp_content_path']}\n\n" .
               
               "## Configuration\n" .
               "- **WP_DEBUG:** " . ($site_info['configuration']['wp_debug'] ? 'true' : 'false') . "\n" .
               "- **WP_DEBUG_LOG:** " . ($site_info['configuration']['wp_debug_log'] ? 'true' : 'false') . "\n" .
               "- **WP_DEBUG_DISPLAY:** " . ($site_info['configuration']['wp_debug_display'] ? 'true' : 'false') . "\n" .
               "- **SCRIPT_DEBUG:** " . ($site_info['configuration']['script_debug'] ? 'true' : 'false') . "\n" .
               "- **WP_CACHE:** " . ($site_info['configuration']['wp_cache'] ? 'true' : 'false') . "\n" .
               "- **FORCE_SSL_ADMIN:** " . ($site_info['configuration']['force_ssl_admin'] ? 'true' : 'false') . "\n\n" .
               
               "## Claude Code Plugin Status\n" .
               "- **WP-CLI Available:** " . (WP_Claude_Code_WP_CLI_Bridge::is_wp_cli_available() ? 'Yes' : 'No') . "\n" .
               "- **LiteLLM Endpoint:** " . ($this->settings['litellm_endpoint'] ?? 'Not configured') . "\n" .
               "- **API Key Configured:** " . (!empty($this->settings['api_key']) ? 'Yes' : 'No') . "\n" .
               "- **Model:** " . ($this->settings['model'] ?? 'Not set') . "\n";
    }
    
    private function get_content_list($post_type = 'post', $status = 'any', $limit = 20) {
        $args = array(
            'post_type' => $post_type,
            'post_status' => $status,
            'numberposts' => $limit,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $posts = get_posts($args);
        
        if (empty($posts)) {
            return "No " . ($post_type === 'any' ? 'content' : $post_type . 's') . " found" . 
                   ($status !== 'any' ? " with status '$status'" : '') . ".";
        }
        
        $output = "# WordPress " . ucfirst($post_type) . "s\n\n";
        $output .= "**Total Found:** " . count($posts) . " " . ($post_type === 'any' ? 'items' : $post_type . 's') . "\n";
        
        if ($status !== 'any') {
            $output .= "**Status Filter:** " . ucfirst($status) . "\n";
        }
        
        $output .= "\n";
        
        foreach ($posts as $post) {
            $status_icon = $this->get_status_icon($post->post_status);
            $output .= "## {$status_icon} {$post->post_title}\n";
            $output .= "- **ID:** {$post->ID}\n";
            $output .= "- **Status:** " . ucfirst($post->post_status) . "\n";
            $output .= "- **Type:** {$post->post_type}\n";
            $output .= "- **Author:** " . get_the_author_meta('display_name', $post->post_author) . "\n";
            $output .= "- **Date:** {$post->post_date}\n";
            $output .= "- **Modified:** {$post->post_modified}\n";
            
            if (!empty($post->post_excerpt)) {
                $output .= "- **Excerpt:** " . substr(strip_tags($post->post_excerpt), 0, 100) . "...\n";
            } elseif (!empty($post->post_content)) {
                $excerpt = substr(strip_tags($post->post_content), 0, 100);
                $output .= "- **Content Preview:** " . $excerpt . (strlen($post->post_content) > 100 ? '...' : '') . "\n";
            }
            
            // Get categories/terms if it's a post
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
            
            $output .= "- **Edit URL:** " . admin_url("post.php?post={$post->ID}&action=edit") . "\n";
            $output .= "- **View URL:** " . get_permalink($post->ID) . "\n";
            $output .= "\n";
        }
        
        // Add summary statistics
        $total_posts = wp_count_posts($post_type);
        $output .= "---\n\n";
        $output .= "## Summary Statistics\n";
        if (is_object($total_posts)) {
            foreach (get_object_vars($total_posts) as $status_name => $count) {
                if ($count > 0) {
                    $output .= "- **" . ucfirst($status_name) . ":** $count\n";
                }
            }
        }
        
        return $output;
    }
    
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
            default:
                return '📄';
        }
    }
    
    private function get_theme_info($include_files = true) {
        $current_theme = wp_get_theme();
        $parent_theme = $current_theme->parent();
        
        // Get theme files (conditionally)
        $theme_files = array();
        $theme_dir = get_template_directory();
        if ($include_files && is_dir($theme_dir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir));
            $file_count = 0;
            foreach ($files as $file) {
                if ($file->isFile() && $file_count < 20) { // Limit to first 20 files
                    $relative_path = str_replace($theme_dir . '/', '', $file->getPathname());
                    $theme_files[] = $relative_path;
                    $file_count++;
                }
            }
        }
        
        // Get theme support features
        $theme_supports = array();
        $features_to_check = array(
            'post-thumbnails', 'custom-background', 'custom-header', 'custom-logo',
            'automatic-feed-links', 'html5', 'title-tag', 'customize-selective-refresh-widgets',
            'post-formats', 'editor-styles', 'dark-editor-style', 'responsive-embeds',
            'align-wide', 'editor-color-palette', 'editor-font-sizes', 'menus'
        );
        
        foreach ($features_to_check as $feature) {
            if (current_theme_supports($feature)) {
                $theme_supports[] = $feature;
            }
        }
        
        // Get template files
        $template_files = array();
        if (is_dir($theme_dir)) {
            $php_files = glob($theme_dir . '/*.php');
            foreach ($php_files as $file) {
                $template_files[] = basename($file);
            }
        }
        
        $output = "# Active Theme Information\n\n";
        $output .= "## Theme Details\n";
        $output .= "- **Name:** {$current_theme->get('Name')}\n";
        $output .= "- **Version:** {$current_theme->get('Version')}\n";
        $output .= "- **Author:** {$current_theme->get('Author')}\n";
        $output .= "- **Description:** {$current_theme->get('Description')}\n";
        $output .= "- **Template:** " . get_template() . "\n";
        $output .= "- **Stylesheet:** " . get_stylesheet() . "\n";
        $output .= "- **Theme URI:** {$current_theme->get('ThemeURI')}\n";
        $output .= "- **Text Domain:** {$current_theme->get('TextDomain')}\n";
        
        if ($parent_theme) {
            $output .= "\n## Parent Theme\n";
            $output .= "- **Name:** {$parent_theme->get('Name')}\n";
            $output .= "- **Version:** {$parent_theme->get('Version')}\n";
            $output .= "- **This is a child theme**\n";
        }
        
        $output .= "\n## Theme Support Features\n";
        if (!empty($theme_supports)) {
            foreach ($theme_supports as $feature) {
                $output .= "- ✅ " . ucwords(str_replace('-', ' ', $feature)) . "\n";
            }
        } else {
            $output .= "- No special theme support features detected\n";
        }
        
        $output .= "\n## Template Files\n";
        if (!empty($template_files)) {
            foreach ($template_files as $file) {
                $output .= "- `$file`\n";
            }
        }
        
        $output .= "\n## Theme Directory\n";
        $output .= "- **Path:** $theme_dir\n";
        $output .= "- **URL:** " . get_template_directory_uri() . "\n";
        
        if (!empty($theme_files) && count($theme_files) > 0) {
            $output .= "\n## Theme Files (sample)\n";
            foreach (array_slice($theme_files, 0, 10) as $file) {
                $output .= "- `$file`\n";
            }
            if (count($theme_files) > 10) {
                $output .= "- ... and " . (count($theme_files) - 10) . " more files\n";
            }
        }
        
        // Get customizer settings
        $mods = get_theme_mods();
        if (!empty($mods)) {
            $output .= "\n## Customizer Settings\n";
            $mod_count = 0;
            foreach ($mods as $key => $value) {
                if ($mod_count < 10) { // Limit to 10 settings
                    $output .= "- **$key:** " . (is_string($value) ? $value : '[' . gettype($value) . ']') . "\n";
                    $mod_count++;
                }
            }
            if (count($mods) > 10) {
                $output .= "- ... and " . (count($mods) - 10) . " more settings\n";
            }
        }
        
        return $output;
    }
    
    private function get_database_status($include_tables = true) {
        global $wpdb;
        
        // Get database info
        $db_info = WP_Claude_Code_Database::get_site_info();
        
        // Get table information (conditionally detailed)
        $tables = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_size = 0;
        $table_info = array();
        
        foreach ($tables as $table) {
            $table_size = $table['Data_length'] + $table['Index_length'];
            $total_size += $table_size;
            if ($include_tables) {
                $table_info[] = array(
                    'name' => $table['Name'],
                    'rows' => $table['Rows'],
                    'size' => $table_size,
                    'engine' => $table['Engine'],
                    'collation' => $table['Collation']
                );
            }
        }
        
        // Sort tables by size
        usort($table_info, function($a, $b) {
            return $b['size'] - $a['size'];
        });
        
        // Get database version info
        $mysql_version = $wpdb->get_var("SELECT VERSION()");
        
        // Check database health
        $health_checks = array();
        
        // Check for MyISAM tables (should be InnoDB)
        $myisam_tables = $wpdb->get_results("SHOW TABLE STATUS WHERE Engine = 'MyISAM'", ARRAY_A);
        if (!empty($myisam_tables)) {
            $health_checks[] = "⚠️  Found " . count($myisam_tables) . " MyISAM tables (consider converting to InnoDB)";
        } else {
            $health_checks[] = "✅ All tables using InnoDB engine";
        }
        
        // Check for tables without primary keys
        $tables_without_pk = array();
        foreach ($table_info as $table) {
            $pk_check = $wpdb->get_var("SHOW KEYS FROM `{$table['name']}` WHERE Key_name = 'PRIMARY'");
            if (!$pk_check) {
                $tables_without_pk[] = $table['name'];
            }
        }
        
        if (!empty($tables_without_pk)) {
            $health_checks[] = "⚠️  Tables without primary keys: " . implode(', ', $tables_without_pk);
        } else {
            $health_checks[] = "✅ All tables have primary keys";
        }
        
        $output = "# Database Status & Statistics\n\n";
        $output .= "## Database Connection\n";
        $output .= "- **Host:** {$db_info['database']['host']}\n";
        $output .= "- **Database:** {$db_info['database']['name']}\n";
        $output .= "- **Prefix:** {$db_info['database']['prefix']}\n";
        $output .= "- **Charset:** {$db_info['database']['charset']}\n";
        $output .= "- **Collation:** {$db_info['database']['collate']}\n";
        $output .= "- **MySQL Version:** $mysql_version\n";
        
        $output .= "\n## Database Size\n";
        $output .= "- **Total Size:** " . size_format($total_size) . "\n";
        $output .= "- **Total Tables:** " . count($tables) . "\n";
        
        $output .= "\n## Content Statistics\n";
        $output .= "- **Posts:** {$db_info['tables']['posts']}\n";
        $output .= "- **Users:** {$db_info['tables']['users']}\n";
        $output .= "- **Comments:** {$db_info['tables']['comments']}\n";
        $output .= "- **Options:** {$db_info['tables']['options']}\n";
        $output .= "- **Published Posts:** {$db_info['content']['published_posts']}\n";
        $output .= "- **Published Pages:** {$db_info['content']['published_pages']}\n";
        $output .= "- **Draft Posts:** {$db_info['content']['draft_posts']}\n";
        $output .= "- **Pending Comments:** {$db_info['content']['pending_comments']}\n";
        
        $output .= "\n## Largest Tables\n";
        foreach (array_slice($table_info, 0, 10) as $table) {
            $output .= "- **{$table['name']}:** " . number_format($table['rows']) . " rows, " . size_format($table['size']) . " ({$table['engine']})\n";
        }
        
        $output .= "\n## Database Health Checks\n";
        foreach ($health_checks as $check) {
            $output .= "$check\n";
        }
        
        // Performance recommendations
        $output .= "\n## Performance Recommendations\n";
        if ($total_size > 100 * 1024 * 1024) { // > 100MB
            $output .= "- 🔍 Large database detected (" . size_format($total_size) . ") - consider optimization\n";
        }
        
        if (count($tables) > 50) {
            $output .= "- 📊 Many tables detected (" . count($tables) . ") - may indicate plugin bloat\n";
        }
        
        if ($db_info['content']['pending_comments'] > 100) {
            $output .= "- 💬 Many pending comments - consider review or spam cleanup\n";
        }
        
        $output .= "- 💡 Regular database optimization recommended\n";
        $output .= "- 🔄 Consider automated backups for data protection\n";
        
        return $output;
    }
    
    public function test_connection() {
        $endpoint = $this->settings['litellm_endpoint'] ?? '';
        
        if (empty($endpoint)) {
            return new WP_Error('no_endpoint', 'LiteLLM endpoint not configured');
        }
        
        $test_data = array(
            'model' => $this->settings['model'] ?? 'claude-3-sonnet',
            'messages' => array(
                array('role' => 'user', 'content' => 'Hello, this is a connection test.')
            ),
            'max_tokens' => 50
        );
        
        $response = $this->make_api_request($endpoint, $test_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return array('status' => 'success', 'message' => 'Connection successful');
    }
}