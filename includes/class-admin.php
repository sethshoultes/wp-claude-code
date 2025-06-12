<?php

class WP_Claude_Code_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_claude_code_chat', array($this, 'handle_chat_request'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Claude Code',
            'Claude Code',
            'manage_options',
            'claude-code',
            array($this, 'render_main_page'),
            'dashicons-admin-tools',
            30
        );
        
        add_submenu_page(
            'claude-code',
            'Settings',
            'Settings',
            'manage_options',
            'claude-code-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'claude-code',
            'Debug Configuration',
            'Debug Config',
            'manage_options',
            'claude-code-debug',
            array($this, 'render_debug_page')
        );
        
        add_submenu_page(
            'claude-code',
            'WP-CLI Installer',
            'Install WP-CLI',
            'manage_options',
            'claude-code-wp-cli-installer',
            array($this, 'render_wp_cli_installer_page')
        );
    }
    
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'claude-code') === false) {
            return;
        }
        
        wp_enqueue_script(
            'claude-code-admin',
            WP_CLAUDE_CODE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WP_CLAUDE_CODE_VERSION,
            true
        );
        
        wp_enqueue_style(
            'claude-code-admin',
            WP_CLAUDE_CODE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_CLAUDE_CODE_VERSION
        );
        
        wp_localize_script('claude-code-admin', 'claudeCode', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('claude_code_nonce'),
            'currentUser' => wp_get_current_user()->display_name
        ));
    }
    
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1>Claude Code Interface</h1>
            
            <div id="claude-code-interface">
                <div class="chat-container">
                    <div class="chat-header">
                        <h3>WordPress Development Assistant</h3>
                        <div class="status-indicator">
                            <span class="status-dot"></span>
                            <span id="connection-status">Connecting...</span>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chat-messages">
                        <div class="system-message">
                            <p>Welcome to Claude Code for WordPress! I can help you with:</p>
                            <ul>
                                <li>Theme and plugin development</li>
                                <li>Database queries and content management</li>
                                <li>WP-CLI commands and site management</li>
                                <li>Code analysis and debugging</li>
                                <li>Creating staging environments</li>
                            </ul>
                            <p>What would you like to work on?</p>
                        </div>
                    </div>
                    
                    <div class="chat-input-container">
                        <textarea 
                            id="chat-input" 
                            placeholder="Ask me to help with your WordPress development..."
                            rows="3"
                        ></textarea>
                        <div class="input-actions">
                            <button id="send-message" class="button button-primary">Send</button>
                            <button id="clear-chat" class="button">Clear</button>
                        </div>
                    </div>
                </div>
                
                <div class="tools-sidebar">
                    <h4>Available Tools</h4>
                    <div class="tool-status">
                        <div class="tool-item" data-tool="file_operations">
                            <span class="tool-icon">📁</span>
                            <span class="tool-name">File Operations</span>
                            <span class="tool-status-dot active"></span>
                        </div>
                        <div class="tool-item" data-tool="database">
                            <span class="tool-icon">🗄️</span>
                            <span class="tool-name">Database</span>
                            <span class="tool-status-dot active"></span>
                        </div>
                        <div class="tool-item" data-tool="wp_cli">
                            <span class="tool-icon">⚡</span>
                            <span class="tool-name">WP-CLI</span>
                            <span class="tool-status-dot active"></span>
                        </div>
                        <div class="tool-item" data-tool="staging">
                            <span class="tool-icon">🚀</span>
                            <span class="tool-name">Staging</span>
                            <span class="tool-status-dot"></span>
                        </div>
                    </div>
                    
                    <div class="quick-actions">
                        <h4>Quick Actions</h4>
                        <button class="button action-btn" data-action="site-info">Site Info</button>
                        <button class="button action-btn" data-action="plugin-list">List Plugins</button>
                        <button class="button action-btn" data-action="theme-info">Active Theme</button>
                        <button class="button action-btn" data-action="db-status">DB Status</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = get_option('wp_claude_code_settings', array());
        $using_memberpress_config = !empty($settings['use_memberpress_ai_config']);
        ?>
        <div class="wrap">
            <h1>Claude Code Settings</h1>
            
            <?php if ($using_memberpress_config): ?>
                <div class="notice notice-info">
                    <p><strong>Integration Detected:</strong> This plugin is configured to use your existing MemberPress AI Assistant LiteLLM proxy and API configuration. The endpoint and API key will be automatically loaded from MemberPress AI settings.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('claude_code_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Use MemberPress AI Configuration</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="use_memberpress_ai_config" 
                                       value="1" 
                                       <?php checked(!empty($settings['use_memberpress_ai_config'])); ?> />
                                Automatically use LiteLLM proxy and API key from MemberPress AI Assistant
                            </label>
                            <p class="description">If enabled, will use the existing LiteLLM configuration from MemberPress AI</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">LiteLLM Endpoint</th>
                        <td>
                            <input type="url" 
                                   name="litellm_endpoint" 
                                   value="<?php echo esc_attr($settings['litellm_endpoint'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="http://localhost:4000" />
                            <p class="description">Your LiteLLM proxy endpoint URL</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="password" 
                                   name="api_key" 
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                                   class="regular-text" 
                                   <?php echo $using_memberpress_config ? 'placeholder="Auto-loaded from MemberPress AI"' : ''; ?> />
                            <p class="description">
                                <?php if ($using_memberpress_config): ?>
                                    Will be auto-loaded from MemberPress AI. Leave blank to use automatic detection, or enter manually to override.
                                <?php else: ?>
                                    API key for your LiteLLM proxy (if required)
                                <?php endif; ?>
                            </p>
                            <?php if ($using_memberpress_config): ?>
                                <p><a href="<?php echo admin_url('admin.php?page=claude-code-debug'); ?>">🔍 Debug Configuration Loading</a></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <select name="model">
                                <option value="claude-3-sonnet" <?php selected($settings['model'] ?? '', 'claude-3-sonnet'); ?>>Claude 3 Sonnet</option>
                                <option value="claude-3-opus" <?php selected($settings['model'] ?? '', 'claude-3-opus'); ?>>Claude 3 Opus</option>
                                <option value="claude-3-haiku" <?php selected($settings['model'] ?? '', 'claude-3-haiku'); ?>>Claude 3 Haiku</option>
                                <option value="gpt-4" <?php selected($settings['model'] ?? '', 'gpt-4'); ?>>GPT-4</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Max Tokens</th>
                        <td>
                            <input type="number" 
                                   name="max_tokens" 
                                   value="<?php echo esc_attr($settings['max_tokens'] ?? 4000); ?>" 
                                   min="100" 
                                   max="8000" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Enabled Tools</th>
                        <td>
                            <?php
                            $enabled_tools = $settings['enabled_tools'] ?? array();
                            $available_tools = array(
                                'file_read' => 'File Reading',
                                'file_edit' => 'File Editing',
                                'wp_cli' => 'WP-CLI Commands',
                                'db_query' => 'Database Queries',
                                'staging' => 'Staging Management'
                            );
                            
                            foreach ($available_tools as $tool => $label) {
                                $checked = in_array($tool, $enabled_tools) ? 'checked' : '';
                                echo "<label><input type='checkbox' name='enabled_tools[]' value='$tool' $checked> $label</label><br>";
                            }
                            ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="test-connection">
                <h3>Test Connection</h3>
                <button id="test-connection" class="button">Test LiteLLM Connection</button>
                <div id="connection-result"></div>
            </div>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'claude_code_settings_nonce')) {
            wp_die('Security check failed');
        }
        
        $settings = array(
            'use_memberpress_ai_config' => !empty($_POST['use_memberpress_ai_config']),
            'litellm_endpoint' => sanitize_url($_POST['litellm_endpoint']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'model' => sanitize_text_field($_POST['model']),
            'max_tokens' => absint($_POST['max_tokens']),
            'enabled_tools' => array_map('sanitize_text_field', $_POST['enabled_tools'] ?? array())
        );
        
        update_option('wp_claude_code_settings', $settings);
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1>Claude Code Configuration Debug</h1>
            
            <?php
            // Include the debug script
            include WP_CLAUDE_CODE_PLUGIN_PATH . 'debug-config.php';
            ?>
        </div>
        <?php
    }
    
    public function render_wp_cli_installer_page() {
        $wp_cli_available = WP_Claude_Code_WP_CLI_Bridge::is_wp_cli_available();
        ?>
        <div class="wrap">
            <h1>WP-CLI Installer</h1>
            
            <?php if ($wp_cli_available): ?>
                <div class="notice notice-success">
                    <p><strong>✅ WP-CLI is already installed and working!</strong></p>
                </div>
                
                <?php
                $result = WP_Claude_Code_WP_CLI_Bridge::execute('core version');
                if (!is_wp_error($result) && $result['success']) {
                    echo "<p><strong>WordPress Version:</strong> " . esc_html($result['output']) . "</p>";
                }
                ?>
                
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>⚠️ WP-CLI is not available on this system.</strong></p>
                    <p>Don't worry! Claude Code works great with WordPress native functions, but WP-CLI provides additional powerful features.</p>
                </div>
                
                <div class="wp-cli-installation">
                    <h2>Universal WP-CLI Installation</h2>
                    <p>Our installation script works on:</p>
                    <ul>
                        <li>🌐 <strong>Shared Hosting</strong> (cPanel, Plesk, etc.)</li>
                        <li>🐧 <strong>VPS/Dedicated Servers</strong> (Ubuntu, CentOS, etc.)</li>
                        <li>🐳 <strong>Docker Containers</strong></li>
                        <li>💻 <strong>Local Development</strong> (Local by Flywheel, MAMP, etc.)</li>
                        <li>☁️ <strong>Cloud Hosting</strong> (AWS, DigitalOcean, etc.)</li>
                    </ul>
                    
                    <h3>Installation Options:</h3>
                    
                    <div class="installation-methods">
                        <div class="method-card">
                            <h4>🚀 Option 1: Automatic Installation (Recommended)</h4>
                            <p>Copy and paste this command in your terminal:</p>
                            <div class="code-block">
                                <code>cd <?php echo esc_html(ABSPATH); ?>wp-content/plugins/wp-claude-code && ./install-wp-cli.sh</code>
                                <button class="button copy-command" data-command="cd <?php echo esc_html(ABSPATH); ?>wp-content/plugins/wp-claude-code && ./install-wp-cli.sh">Copy</button>
                            </div>
                        </div>
                        
                        <div class="method-card">
                            <h4>📱 Option 2: cPanel File Manager (Shared Hosting)</h4>
                            <ol>
                                <li>Open cPanel File Manager</li>
                                <li>Navigate to: <code><?php echo esc_html(ABSPATH); ?>wp-content/plugins/wp-claude-code/</code></li>
                                <li>Right-click <code>install-wp-cli.sh</code> → Execute</li>
                                <li>Or open Terminal and run the script</li>
                            </ol>
                        </div>
                        
                        <div class="method-card">
                            <h4>💻 Option 3: SSH Access</h4>
                            <p>Connect via SSH and run:</p>
                            <div class="code-block">
                                <code>cd <?php echo esc_html(ABSPATH); ?>wp-content/plugins/wp-claude-code</code><br>
                                <code>chmod +x install-wp-cli.sh</code><br>
                                <code>./install-wp-cli.sh</code>
                                <button class="button copy-commands" data-commands="cd <?php echo esc_html(ABSPATH); ?>wp-content/plugins/wp-claude-code|chmod +x install-wp-cli.sh|./install-wp-cli.sh">Copy All</button>
                            </div>
                        </div>
                        
                        <div class="method-card">
                            <h4>📋 Option 4: Manual Installation</h4>
                            <p>For advanced users or restricted environments:</p>
                            <div class="code-block">
                                <code>curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar</code><br>
                                <code>php wp-cli.phar --info</code><br>
                                <code>chmod +x wp-cli.phar</code><br>
                                <code>sudo mv wp-cli.phar /usr/local/bin/wp</code>
                                <button class="button copy-commands" data-commands="curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar|php wp-cli.phar --info|chmod +x wp-cli.phar|sudo mv wp-cli.phar /usr/local/bin/wp">Copy All</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="installation-help">
                        <h3>Need Help?</h3>
                        <ul>
                            <li><strong>Permission Denied:</strong> Try <code>chmod +x install-wp-cli.sh</code> first</li>
                            <li><strong>Shared Hosting:</strong> WP-CLI will install locally and work perfectly</li>
                            <li><strong>No Terminal Access:</strong> Contact your hosting provider for SSH access</li>
                            <li><strong>Installation Failed:</strong> Check the debug page for detailed error information</li>
                        </ul>
                        
                        <p><strong>Remember:</strong> Claude Code works great even without WP-CLI! The native WordPress functions provide all the core functionality you need.</p>
                    </div>
                </div>
                
                <button id="check-wp-cli" class="button button-secondary">🔄 Recheck WP-CLI Installation</button>
                
            <?php endif; ?>
            
            <style>
            .installation-methods {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            .method-card {
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                background: #fff;
            }
            .method-card h4 {
                margin-top: 0;
                color: #1d2327;
            }
            .code-block {
                background: #2d3748;
                color: #e2e8f0;
                padding: 15px;
                border-radius: 4px;
                margin: 10px 0;
                position: relative;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.4;
            }
            .code-block code {
                background: none;
                color: inherit;
            }
            .copy-command, .copy-commands {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 11px;
            }
            .installation-help {
                background: #f8f9fa;
                border-left: 3px solid #0073aa;
                padding: 15px;
                margin: 20px 0;
            }
            </style>
            
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Copy command functionality
                document.querySelectorAll('.copy-command').forEach(function(button) {
                    button.addEventListener('click', function() {
                        const command = this.dataset.command;
                        navigator.clipboard.writeText(command).then(function() {
                            button.textContent = 'Copied!';
                            setTimeout(() => button.textContent = 'Copy', 2000);
                        });
                    });
                });
                
                document.querySelectorAll('.copy-commands').forEach(function(button) {
                    button.addEventListener('click', function() {
                        const commands = this.dataset.commands.split('|').join('\n');
                        navigator.clipboard.writeText(commands).then(function() {
                            button.textContent = 'Copied!';
                            setTimeout(() => button.textContent = 'Copy All', 2000);
                        });
                    });
                });
                
                // Recheck WP-CLI
                document.getElementById('check-wp-cli')?.addEventListener('click', function() {
                    window.location.reload();
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function handle_chat_request() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $message = sanitize_textarea_field($_POST['message']);
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        
        if (empty($conversation_id)) {
            $conversation_id = uniqid('conv_');
        }
        
        // Save user message
        $this->save_message($conversation_id, 'user', $message);
        
        // Process with Claude API
        $claude_api = new WP_Claude_Code_Claude_API();
        $response = $claude_api->send_message($message, $conversation_id);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }
        
        // Save assistant response
        $this->save_message($conversation_id, 'assistant', $response['content'], $response['tools_used'] ?? null);
        
        wp_send_json_success(array(
            'response' => $response['content'],
            'conversation_id' => $conversation_id,
            'tools_used' => $response['tools_used'] ?? array()
        ));
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
}