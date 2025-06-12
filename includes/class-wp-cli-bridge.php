<?php

class WP_Claude_Code_WP_CLI_Bridge {
    
    public static function execute($command) {
        if (!self::is_wp_cli_available()) {
            // Fallback to WordPress native functions
            return self::execute_native_alternative($command);
        }
        
        if (!self::is_safe_command($command)) {
            return new WP_Error('unsafe_command', 'Command is not allowed for security reasons');
        }
        
        $full_command = self::$wp_cli_path . ' ' . $command . ' --path=' . escapeshellarg(ABSPATH);
        
        $output = array();
        $return_var = 0;
        
        exec($full_command . ' 2>&1', $output, $return_var);
        
        return array(
            'command' => $command,
            'output' => implode("\n", $output),
            'return_code' => $return_var,
            'success' => $return_var === 0
        );
    }
    
    public static function get_plugin_list() {
        return self::execute('plugin list --format=json');
    }
    
    public static function get_theme_list() {
        return self::execute('theme list --format=json');
    }
    
    public static function get_user_list() {
        return self::execute('user list --format=json');
    }
    
    public static function get_site_info() {
        $info = array();
        
        $commands = array(
            'core_version' => 'core version',
            'db_size' => 'db size --format=json',
            'option_count' => 'option list --format=count',
            'plugin_count' => 'plugin list --format=count',
            'theme_count' => 'theme list --format=count',
            'user_count' => 'user list --format=count'
        );
        
        foreach ($commands as $key => $command) {
            $result = self::execute($command);
            if (!is_wp_error($result) && $result['success']) {
                $info[$key] = $result['output'];
            }
        }
        
        return $info;
    }
    
    public static function create_post($title, $content, $post_type = 'post', $status = 'draft') {
        $command = sprintf(
            'post create --post_title=%s --post_content=%s --post_type=%s --post_status=%s --porcelain',
            escapeshellarg($title),
            escapeshellarg($content),
            escapeshellarg($post_type),
            escapeshellarg($status)
        );
        
        return self::execute($command);
    }
    
    public static function update_option($option_name, $option_value) {
        $command = sprintf(
            'option update %s %s',
            escapeshellarg($option_name),
            escapeshellarg($option_value)
        );
        
        return self::execute($command);
    }
    
    public static function flush_cache() {
        $commands = array(
            'cache flush',
            'rewrite flush'
        );
        
        $results = array();
        
        foreach ($commands as $command) {
            $results[] = self::execute($command);
        }
        
        return $results;
    }
    
    public static function run_cron() {
        return self::execute('cron event run --due-now');
    }
    
    public static function is_wp_cli_available() {
        // Check multiple possible locations for WP-CLI
        $wp_cli_paths = array(
            'wp',                           // Global install
            '/usr/local/bin/wp',           // Standard install location  
            '/usr/bin/wp',                 // Alternative location
            '/opt/wp-cli/wp-cli.phar',     // Local by Flywheel possible location
            ABSPATH . '../wp-cli.phar',    // Site-specific install
        );
        
        foreach ($wp_cli_paths as $path) {
            $output = array();
            $return_var = 0;
            exec("$path --version 2>/dev/null", $output, $return_var);
            
            if ($return_var === 0) {
                // Store the working path for later use
                self::$wp_cli_path = $path;
                return true;
            }
        }
        
        return false;
    }
    
    private static $wp_cli_path = 'wp';
    
    private static function is_safe_command($command) {
        $dangerous_commands = array(
            'core download',
            'core update',
            'db drop',
            'db reset',
            'eval-file',
            'shell',
            'server'
        );
        
        foreach ($dangerous_commands as $dangerous) {
            if (strpos($command, $dangerous) === 0) {
                return false;
            }
        }
        
        // Block commands that could modify the filesystem outside of content
        if (preg_match('/rm|mv|cp|chmod|chown/', $command)) {
            return false;
        }
        
        return true;
    }
    
    public static function get_available_commands() {
        $safe_commands = array(
            'Information' => array(
                'core version' => 'Get WordPress version',
                'plugin list' => 'List all plugins',
                'theme list' => 'List all themes',
                'user list' => 'List all users',
                'post list' => 'List posts',
                'option list' => 'List options',
                'db size' => 'Get database size'
            ),
            'Content Management' => array(
                'post create' => 'Create a new post',
                'post update' => 'Update a post',
                'post delete' => 'Delete a post',
                'comment list' => 'List comments',
                'media list' => 'List media files'
            ),
            'Maintenance' => array(
                'cache flush' => 'Flush all caches',
                'rewrite flush' => 'Flush rewrite rules',
                'cron event run' => 'Run scheduled events',
                'db optimize' => 'Optimize database',
                'transient delete' => 'Delete transients'
            ),
            'Plugins & Themes' => array(
                'plugin activate' => 'Activate a plugin',
                'plugin deactivate' => 'Deactivate a plugin',
                'theme activate' => 'Activate a theme',
                'plugin status' => 'Get plugin status'
            )
        );
        
        return $safe_commands;
    }
    
    /**
     * Execute WordPress native alternatives when WP-CLI is not available
     */
    private static function execute_native_alternative($command) {
        $parts = explode(' ', $command);
        $main_command = $parts[0] ?? '';
        $sub_command = $parts[1] ?? '';
        
        try {
            switch ($main_command) {
                case 'core':
                    return self::handle_core_commands($sub_command, $parts);
                    
                case 'plugin':
                    return self::handle_plugin_commands($sub_command, $parts);
                    
                case 'theme':
                    return self::handle_theme_commands($sub_command, $parts);
                    
                case 'user':
                    return self::handle_user_commands($sub_command, $parts);
                    
                case 'post':
                    return self::handle_post_commands($sub_command, $parts);
                    
                case 'option':
                    return self::handle_option_commands($sub_command, $parts);
                    
                case 'db':
                    return self::handle_db_commands($sub_command, $parts);
                    
                case 'cache':
                    return self::handle_cache_commands($sub_command, $parts);
                    
                default:
                    return array(
                        'command' => $command,
                        'output' => "Command '$main_command' not supported in native mode",
                        'return_code' => 1,
                        'success' => false
                    );
            }
        } catch (Exception $e) {
            return array(
                'command' => $command,
                'output' => 'Error: ' . $e->getMessage(),
                'return_code' => 1,
                'success' => false
            );
        }
    }
    
    private static function handle_core_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'version':
                global $wp_version;
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $wp_version,
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_plugin_commands($sub_command, $parts) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        switch ($sub_command) {
            case 'list':
                $plugins = get_plugins();
                $active_plugins = get_option('active_plugins', array());
                $output = array();
                
                $format = self::get_format_from_parts($parts);
                
                foreach ($plugins as $plugin_file => $plugin_data) {
                    $status = in_array($plugin_file, $active_plugins) ? 'active' : 'inactive';
                    
                    if ($format === 'json') {
                        $output[] = array(
                            'name' => $plugin_data['Name'],
                            'status' => $status,
                            'version' => $plugin_data['Version'],
                            'file' => $plugin_file
                        );
                    } else {
                        $output[] = sprintf("%-30s %s %s", 
                            $plugin_data['Name'], 
                            $status, 
                            $plugin_data['Version']
                        );
                    }
                }
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $format === 'json' ? json_encode($output) : implode("\\n", $output),
                    'return_code' => 0,
                    'success' => true
                );
                
            case 'status':
                if (isset($parts[2])) {
                    $plugin_slug = $parts[2];
                    $active_plugins = get_option('active_plugins', array());
                    $is_active = false;
                    
                    foreach ($active_plugins as $plugin) {
                        if (strpos($plugin, $plugin_slug) !== false) {
                            $is_active = true;
                            break;
                        }
                    }
                    
                    return array(
                        'command' => implode(' ', $parts),
                        'output' => $is_active ? 'active' : 'inactive',
                        'return_code' => 0,
                        'success' => true
                    );
                }
                break;
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_theme_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'list':
                $themes = wp_get_themes();
                $current_theme = get_stylesheet();
                $output = array();
                
                $format = self::get_format_from_parts($parts);
                
                foreach ($themes as $theme_slug => $theme) {
                    $status = ($theme_slug === $current_theme) ? 'active' : 'inactive';
                    
                    if ($format === 'json') {
                        $output[] = array(
                            'name' => $theme->get('Name'),
                            'status' => $status,
                            'version' => $theme->get('Version'),
                            'slug' => $theme_slug
                        );
                    } else {
                        $output[] = sprintf("%-30s %s %s", 
                            $theme->get('Name'), 
                            $status, 
                            $theme->get('Version')
                        );
                    }
                }
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $format === 'json' ? json_encode($output) : implode("\\n", $output),
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_user_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'list':
                $users = get_users();
                $output = array();
                
                $format = self::get_format_from_parts($parts);
                
                foreach ($users as $user) {
                    if ($format === 'json') {
                        $output[] = array(
                            'ID' => $user->ID,
                            'user_login' => $user->user_login,
                            'display_name' => $user->display_name,
                            'user_email' => $user->user_email,
                            'roles' => $user->roles
                        );
                    } else {
                        $output[] = sprintf("%-5d %-20s %-30s %s", 
                            $user->ID, 
                            $user->user_login, 
                            $user->display_name,
                            implode(',', $user->roles)
                        );
                    }
                }
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $format === 'json' ? json_encode($output) : implode("\\n", $output),
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_post_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'list':
                $posts = get_posts(array(
                    'numberposts' => 20,
                    'post_status' => 'any'
                ));
                
                $output = array();
                $format = self::get_format_from_parts($parts);
                
                foreach ($posts as $post) {
                    if ($format === 'json') {
                        $output[] = array(
                            'ID' => $post->ID,
                            'post_title' => $post->post_title,
                            'post_status' => $post->post_status,
                            'post_type' => $post->post_type,
                            'post_date' => $post->post_date
                        );
                    } else {
                        $output[] = sprintf("%-5d %-30s %-10s %s", 
                            $post->ID, 
                            substr($post->post_title, 0, 30), 
                            $post->post_status,
                            $post->post_type
                        );
                    }
                }
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $format === 'json' ? json_encode($output) : implode("\\n", $output),
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_option_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'get':
                if (isset($parts[2])) {
                    $option_name = $parts[2];
                    $value = get_option($option_name);
                    
                    $format = self::get_format_from_parts($parts);
                    
                    if ($format === 'json') {
                        $output = json_encode($value);
                    } else {
                        $output = is_array($value) || is_object($value) ? 
                            print_r($value, true) : 
                            (string)$value;
                    }
                    
                    return array(
                        'command' => implode(' ', $parts),
                        'output' => $output,
                        'return_code' => 0,
                        'success' => true
                    );
                }
                break;
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_db_commands($sub_command, $parts) {
        global $wpdb;
        
        switch ($sub_command) {
            case 'size':
                $result = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
                $total_size = 0;
                
                foreach ($result as $table) {
                    $total_size += $table['Data_length'] + $table['Index_length'];
                }
                
                $format = self::get_format_from_parts($parts);
                
                if ($format === 'json') {
                    $output = json_encode(array('size' => $total_size, 'formatted' => size_format($total_size)));
                } else {
                    $output = size_format($total_size);
                }
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => $output,
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function handle_cache_commands($sub_command, $parts) {
        switch ($sub_command) {
            case 'flush':
                wp_cache_flush();
                
                return array(
                    'command' => implode(' ', $parts),
                    'output' => 'Cache flushed successfully',
                    'return_code' => 0,
                    'success' => true
                );
                
            default:
                return self::unsupported_command($parts);
        }
    }
    
    private static function get_format_from_parts($parts) {
        foreach ($parts as $part) {
            if (strpos($part, '--format=') === 0) {
                return substr($part, 9);
            }
        }
        return 'table';
    }
    
    private static function unsupported_command($parts) {
        return array(
            'command' => implode(' ', $parts),
            'output' => 'Command not supported in native WordPress mode',
            'return_code' => 1,
            'success' => false
        );
    }
}