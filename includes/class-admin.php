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
        add_action('wp_ajax_claude_code_set_model', array($this, 'handle_set_model'));
        add_action('wp_ajax_claude_code_get_user_model', array($this, 'handle_get_user_model'));
        add_action('wp_ajax_claude_code_debug_image', array($this, 'handle_debug_image'));
        add_action('wp_ajax_claude_code_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_claude_code_save_provider_settings', array($this, 'handle_save_provider_settings'));
        add_action('wp_ajax_claude_code_detect_configuration', array($this, 'handle_detect_configuration'));
        add_action('wp_ajax_claude_code_test_provider_connection', array($this, 'handle_test_provider_connection'));
        add_action('wp_ajax_claude_code_get_available_models', array($this, 'handle_get_available_models'));
        add_action('wp_ajax_claude_code_refresh_models', array($this, 'handle_refresh_models'));
        add_action('wp_ajax_claude_code_get_provider_status', array($this, 'handle_get_provider_status'));
        add_action('wp_ajax_wp_claude_code_debug_logs', array($this, 'handle_debug_logs'));
        
        // Ensure backward compatibility by migrating old settings
        add_action('admin_init', array($this, 'maybe_migrate_settings'));
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
        
        // Add debug page conditionally when debug mode is enabled
        $settings = get_option('wp_claude_code_settings', array());
        if (!empty($settings['debug_mode'])) {
            add_submenu_page(
                'claude-code',
                'Debug',
                'Debug',
                'manage_options',
                'claude-code-debug',
                array($this, 'render_debug_page')
            );
        }
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
                        <div class="header-left">
                            <h3>WordPress Development Assistant</h3>
                            <div class="model-selector">
                                <select id="model-selector">
                                    <option value="loading">Loading models...</option>
                                </select>
                                <button id="refresh-models" class="button button-small" title="Refresh available models">🔄</button>
                            </div>
                        </div>
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
                                        </ul>
                            <p>What would you like to work on?</p>
                        </div>
                    </div>
                    
                    <div class="chat-input-container">
                        <div class="file-attachments" id="file-attachments" style="display: none;">
                            <div class="attachments-header">
                                <span>📎 Attachments:</span>
                                <button type="button" id="clear-attachments" class="button-link">Clear All</button>
                            </div>
                            <div class="attachments-list" id="attachments-list"></div>
                        </div>
                        
                        <textarea 
                            id="chat-input" 
                            placeholder="Ask me to help with your WordPress development..."
                            rows="3"
                        ></textarea>
                        
                        <div class="input-actions">
                            <div class="file-upload-wrapper">
                                <input type="file" id="file-upload" accept=".txt,.csv,.json,.pdf,.zip,.html,.css,.js,.sql,.jpg,.jpeg,.png,.gif,.webp" multiple style="display: none;">
                                <button type="button" id="attach-file" class="button" title="Attach File">📎</button>
                            </div>
                            <button id="send-message" class="button button-primary">Send</button>
                            <button id="clear-chat" class="button">Clear</button>
                        </div>
                    </div>
                </div>
                
                <div class="tools-sidebar">
                    <!-- Conversation History Section -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <h4>💬 Conversations</h4>
                            <button id="new-conversation" class="button button-small" title="Start New Conversation">+</button>
                        </div>
                        <div class="conversation-list" id="conversation-list">
                            <div class="loading-placeholder">Loading conversations...</div>
                        </div>
                    </div>
                    
                    <!-- Saved Prompts Section -->
                    <div class="sidebar-section">
                        <div class="section-header">
                            <h4>💾 Saved Prompts</h4>
                            <button id="save-prompt" class="button button-small" title="Save Current Message as Prompt">💾</button>
                        </div>
                        <div class="prompt-categories">
                            <select id="prompt-category-filter">
                                <option value="">All Categories</option>
                            </select>
                        </div>
                        <div class="prompt-list" id="prompt-list">
                            <div class="loading-placeholder">Loading prompts...</div>
                        </div>
                    </div>
                    
                    <!-- Available Tools Section -->
                    <div class="sidebar-section">
                        <h4>🛠️ Available Tools</h4>
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
                            <div class="tool-item" data-tool="plugin_repository">
                                <span class="tool-icon">🔍</span>
                                <span class="tool-name">Plugin Repository</span>
                                <span class="tool-status-dot active"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions Section -->
                    <div class="sidebar-section">
                        <h4>⚡ Quick Actions</h4>
                        <button class="button action-btn" data-action="site-info">Site Info</button>
                        <button class="button action-btn" data-action="plugin-list">List Plugins</button>
                        <button class="button action-btn" data-action="theme-info">Active Theme</button>
                        <button class="button action-btn" data-action="db-status">DB Status</button>
                        <button class="button action-btn" data-action="list-posts">List Posts</button>
                        <button class="button action-btn" data-action="list-pages">List Pages</button>
                        <button class="button action-btn" data-action="create-post">Create Post</button>
                        <button class="button action-btn" data-action="create-page">Create Page</button>
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
        
        $settings = $this->get_settings();
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1>Claude Code Settings</h1>
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
                <a href="?page=claude-code-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">⚙️ General</a>
                <a href="?page=claude-code-settings&tab=openai" class="nav-tab <?php echo $active_tab === 'openai' ? 'nav-tab-active' : ''; ?>">🤖 OpenAI</a>
                <a href="?page=claude-code-settings&tab=claude" class="nav-tab <?php echo $active_tab === 'claude' ? 'nav-tab-active' : ''; ?>">🧠 Claude</a>
                <a href="?page=claude-code-settings&tab=tools" class="nav-tab <?php echo $active_tab === 'tools' ? 'nav-tab-active' : ''; ?>">🛠️ Tools & Interface</a>
                <a href="?page=claude-code-settings&tab=wp-cli" class="nav-tab <?php echo $active_tab === 'wp-cli' ? 'nav-tab-active' : ''; ?>">⚡ WP-CLI Installer</a>
            </nav>
            
            <!-- Tab Content -->
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'openai':
                        $this->render_provider_tab('openai', $settings);
                        break;
                    case 'claude':
                        $this->render_provider_tab('claude', $settings);
                        break;
                    case 'tools':
                        $this->render_tools_tab($settings);
                        break;
                    case 'wp-cli':
                        $this->render_wp_cli_tab($settings);
                        break;
                    case 'general':
                    default:
                        $this->render_general_tab($settings);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render standalone debug page
     */
    public function render_debug_page() {
        ?>
        <div class="wrap">
            <h1>Claude Code Debug</h1>
            <?php
            // Include the debug configuration file which contains all the debug functionality
            include_once WP_CLAUDE_CODE_PLUGIN_PATH . 'debug-config.php';
            ?>
        </div>
        <?php
    }
    
    /**
     * Render unified provider configuration tab (OpenAI or Claude)
     */
    private function render_provider_tab($provider, $settings) {
        $provider_config = $this->get_provider_config($provider);
        $tab_id = $provider . '-tab';
        
        ?>
        <div class="tab-panel" id="<?php echo esc_attr($tab_id); ?>">
            <form method="post" action="" class="provider-settings-form" data-provider="<?php echo esc_attr($provider); ?>">
                <?php wp_nonce_field('claude_code_settings_nonce'); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr($provider); ?>" />
                
                <?php $this->render_provider_header($provider, $provider_config, $settings); ?>
                
                <table class="form-table">
                    <?php 
                    $this->render_api_key_field($provider, $provider_config, $settings);
                    $this->render_model_selector_field($provider, $provider_config, $settings);
                    $this->render_max_tokens_field($provider, $provider_config, $settings);
                    $this->render_temperature_field($provider, $provider_config, $settings);
                    ?>
                </table>
                
                <?php $this->render_provider_actions($provider, $provider_config); ?>
                
                <div class="connection-result" id="<?php echo esc_attr($provider); ?>-connection-result"></div>
            </form>
        </div>
        <?php
    }
    
    
    /**
     * Render General Settings Tab
     */
    private function render_general_tab($settings) {
        ?>
        <div class="tab-panel" id="general-tab">
            <form method="post" action="">
                <?php wp_nonce_field('claude_code_settings_nonce'); ?>
                <input type="hidden" name="tab" value="general" />
                
                <h2>⚙️ General Settings</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="primary_provider">Primary Provider</label>
                        </th>
                        <td>
                            <select name="primary_provider" id="primary_provider">
                                <option value="litellm_proxy" <?php selected($settings['api_provider'] ?? 'litellm_proxy', 'litellm_proxy'); ?>>LiteLLM Proxy (Recommended - No API key required)</option>
                                <option value="openai_direct" <?php selected($settings['api_provider'] ?? '', 'openai_direct'); ?>>OpenAI Direct (Requires your API key)</option>
                                <option value="claude_direct" <?php selected($settings['api_provider'] ?? '', 'claude_direct'); ?>>Claude Direct (Requires your API key)</option>
                            </select>
                            <p class="description">Choose your AI provider. LiteLLM Proxy is recommended as it requires no setup and includes both Claude and OpenAI models.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="conversation_retention">Conversation Retention</label>
                        </th>
                        <td>
                            <select name="conversation_retention" id="conversation_retention">
                                <option value="30" <?php selected($settings['conversation_retention'] ?? '30', '30'); ?>>30 days</option>
                                <option value="90" <?php selected($settings['conversation_retention'] ?? '', '90'); ?>>90 days</option>
                                <option value="365" <?php selected($settings['conversation_retention'] ?? '', '365'); ?>>1 year</option>
                                <option value="0" <?php selected($settings['conversation_retention'] ?? '', '0'); ?>>Keep forever</option>
                            </select>
                            <p class="description">How long to retain conversation history before automatic cleanup.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="debug_mode">Debug Mode</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="debug_mode"
                                       value="1"
                                       <?php checked(!empty($settings['debug_mode'])); ?> />
                                Enable debug logging
                            </label>
                            <p class="description">Log API requests and responses for troubleshooting. Disable in production.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save General Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render Tools & Interface Settings Tab
     */
    private function render_tools_tab($settings) {
        ?>
        <div class="tab-panel" id="tools-tab">
            <form method="post" action="">
                <?php wp_nonce_field('claude_code_settings_nonce'); ?>
                <input type="hidden" name="tab" value="tools" />
                
                <h2>🛠️ Tools & Interface Settings</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enabled Tools</th>
                        <td>
                            <?php $this->render_enabled_tools_section($settings); ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Chat UI</th>
                        <td>
                            <?php $this->render_chat_ui_section($settings); ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Tools & Interface Settings'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render WP-CLI Installer Tab
     */
    private function render_wp_cli_tab($settings) {
        $wp_cli_available = WP_Claude_Code_WP_CLI_Bridge::is_wp_cli_available();
        ?>
        <div class="tab-panel" id="wp-cli-tab">
            <h2>⚡ WP-CLI Installer</h2>
            
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
                    <h3>Universal WP-CLI Installation</h3>
                    <p>Our installation script works on:</p>
                    <ul>
                        <li>🌐 <strong>Shared Hosting</strong> (cPanel, Plesk, etc.)</li>
                        <li>🐧 <strong>VPS/Dedicated Servers</strong> (Ubuntu, CentOS, etc.)</li>
                        <li>🐳 <strong>Docker Containers</strong></li>
                        <li>💻 <strong>Local Development</strong> (Local by Flywheel, MAMP, etc.)</li>
                        <li>☁️ <strong>Cloud Hosting</strong> (AWS, DigitalOcean, etc.)</li>
                    </ul>
                    
                    <h4>Installation Options:</h4>
                    
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
                        <h4>Need Help?</h4>
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
    
    /**
     * Render debug system status content
     */
    private function render_debug_system_content($settings) {
        ?>
        <div class="system-status-tab">
            <h2>📊 System Status</h2>
            <p>Current plugin configuration and environment information for troubleshooting.</p>
            
            <?php
            // Display sanitized settings (redact sensitive information)
            echo "<h3>Plugin Settings:</h3>";
            $display_settings = $settings;
            foreach ($display_settings as $key => &$value) {
                if (strpos($key, 'key') !== false && !empty($value)) {
                    $value = substr($value, 0, 8) . '...[REDACTED]';
                }
            }
            echo "<pre>" . print_r($display_settings, true) . "</pre>";

            // Test our API client
            echo "<h3>API Client Status:</h3>";
            try {
                require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-claude-api.php';
                $claude_api = new WP_Claude_Code_Claude_API();
                
                $current_provider = $claude_api->get_api_provider();
                $current_model = $claude_api->get_model();
                
                echo "<div style='background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; margin: 15px 0;'>";
                echo "<p><strong>Current Provider:</strong> " . esc_html($current_provider) . "</p>";
                echo "<p><strong>Current Model:</strong> " . esc_html($current_model) . "</p>";
                
                // Check API key configuration based on provider
                if ($current_provider === 'claude' || $current_provider === 'claude_direct') {
                    $api_key_configured = !empty($settings['claude_api_key']);
                    echo "<p><strong>Claude API Key:</strong> " . ($api_key_configured ? "✅ Configured" : "❌ Not configured") . "</p>";
                } elseif ($current_provider === 'openai' || $current_provider === 'openai_direct') {
                    $api_key_configured = !empty($settings['openai_api_key']);
                    echo "<p><strong>OpenAI API Key:</strong> " . ($api_key_configured ? "✅ Configured" : "❌ Not configured") . "</p>";
                } else {
                    echo "<p><strong>API Configuration:</strong> ❌ Unknown provider</p>";
                }
                
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div style='background: #f8d7da; border: 1px solid #dc3232; border-radius: 4px; padding: 15px;'>";
                echo "<strong>Error initializing API client:</strong> " . esc_html($e->getMessage());
                echo "</div>";
            }
            
            // Test connection
            echo "<h3>Connection Test:</h3>";
            echo "<p>Use the button below to test your API connection with the current settings.</p>";
            echo "<button id='test-connection' class='button button-primary'>Test API Connection</button>";
            echo "<div id='connection-test-results' style='margin-top: 15px;'></div>";
            
            // WordPress environment info
            echo "<h3>WordPress Environment:</h3>";
            echo "<div style='background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px;'>";
            echo "<p><strong>WordPress Version:</strong> " . get_bloginfo('version') . "</p>";
            echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
            echo "<p><strong>Plugin Version:</strong> " . (defined('WP_CLAUDE_CODE_VERSION') ? WP_CLAUDE_CODE_VERSION : 'Unknown') . "</p>";
            echo "<p><strong>WordPress Debug:</strong> " . (defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled') . "</p>";
            echo "<p><strong>Plugin Debug Mode:</strong> " . (!empty($settings['debug_mode']) ? '✅ Enabled' : '❌ Disabled') . "</p>";
            echo "</div>";
            
            // Available tools status
            echo "<h3>Available Tools Status:</h3>";
            $enabled_tools = $settings['enabled_tools'] ?? array();
            $available_tools = array(
                'file_read' => 'File Reading',
                'file_edit' => 'File Editing', 
                'wp_cli' => 'WP-CLI Commands',
                'db_query' => 'Database Queries',
                'plugin_repository' => 'Plugin Repository',
                'content_management' => 'Content Management',
            );
            
            echo "<div style='background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px;'>";
            foreach ($available_tools as $tool => $label) {
                $enabled = in_array($tool, $enabled_tools);
                $status = $enabled ? "✅ Enabled" : "❌ Disabled";
                echo "<p><strong>$label:</strong> $status</p>";
            }
            echo "</div>";
            ?>
        </div>
        <?php
    }
    
    // Old debug logs rendering function removed - functionality moved to debug-config.php
    private function render_debug_logs_content() {
        // This function has been moved to debug-config.php - no longer rendering HTML here
    }
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'claude_code_settings_nonce')) {
            wp_die('Security check failed');
        }

        $current_settings = get_option('wp_claude_code_settings', array());
        $tab = $_POST['tab'] ?? 'general';
        
        // Handle tab-specific saving
        switch ($tab) {
            case 'openai':
            case 'claude':
                $this->save_provider_settings($tab, $current_settings);
                break;
            case 'general':
                $this->save_general_settings($current_settings);
                break;
            case 'tools':
                $this->save_tools_settings($current_settings);
                break;
        }
    }
    
    /**
     * Save provider-specific settings (unified for OpenAI and Claude)
     */
    private function save_provider_settings($provider, $current_settings) {
        $provider_config = $this->get_provider_config($provider);
        
        $api_key = sanitize_text_field($_POST[$provider . '_api_key'] ?? '');
        $model = sanitize_text_field($_POST[$provider . '_model'] ?? $provider_config['default_model']);
        $max_tokens = absint($_POST[$provider . '_max_tokens'] ?? 4000);
        $temperature = floatval($_POST[$provider . '_temperature'] ?? 0.7);
        
        // Validate settings
        if (empty($api_key)) {
            echo '<div class="notice notice-error"><p>' . $provider_config['name'] . ' API key is required.</p></div>';
            return;
        }
        
        if (!$this->validate_api_key_format($api_key, $provider)) {
            echo '<div class="notice notice-error"><p>Invalid ' . $provider_config['name'] . ' API key format. Should start with "' . $provider_config['key_prefix'] . '".</p></div>';
            return;
        }
        
        // Update settings
        $current_settings[$provider . '_api_key'] = $api_key;
        $current_settings[$provider . '_model'] = $model;
        $current_settings[$provider . '_max_tokens'] = max(100, min($provider_config['max_tokens'], $max_tokens));
        $current_settings[$provider . '_temperature'] = max(0, min($provider_config['max_temperature'], $temperature));
        
        // Set as primary provider if not set, but don't override user model preferences
        if (empty($current_settings['api_provider'])) {
            $current_settings['api_provider'] = $provider;
            // Only set global model if none exists (fallback)
            if (empty($current_settings['model'])) {
                $current_settings['model'] = $model;
                $current_settings['max_tokens'] = $current_settings[$provider . '_max_tokens'];
            }
        }
        
        update_option('wp_claude_code_settings', $current_settings);
        echo '<div class="notice notice-success"><p>' . $provider_config['name'] . ' settings saved successfully!</p></div>';
    }
    
    /**
     * Save tools-specific settings
     */
    private function save_tools_settings($current_settings) {
        $enabled_tools = array_map('sanitize_text_field', $_POST['enabled_tools'] ?? array());
        $chat_ui_enabled = !empty($_POST['chat_ui_enabled']);
        $chat_ui_renderer = sanitize_text_field($_POST['chat_ui_renderer'] ?? 'client');
        
        // Update tools settings
        $current_settings['enabled_tools'] = $enabled_tools;
        $current_settings['chat_ui_enabled'] = $chat_ui_enabled;
        $current_settings['chat_ui_renderer'] = $chat_ui_renderer;
        
        update_option('wp_claude_code_settings', $current_settings);
        echo '<div class="notice notice-success"><p>Tools & Interface settings saved successfully!</p></div>';
    }
    
    /**
     * Save general settings
     */
    private function save_general_settings($current_settings) {
        $primary_provider = sanitize_text_field($_POST['primary_provider'] ?? 'litellm_proxy');
        $conversation_retention = absint($_POST['conversation_retention'] ?? 30);
        $debug_mode = !empty($_POST['debug_mode']);
        
        // Validate primary provider has been configured (skip validation for LiteLLM proxy)
        if ($primary_provider === 'openai_direct' && empty($current_settings['openai_api_key'])) {
            echo '<div class="notice notice-error"><p>Please configure OpenAI settings first before setting it as primary provider.</p></div>';
            return;
        }
        
        if ($primary_provider === 'claude_direct' && empty($current_settings['claude_api_key'])) {
            echo '<div class="notice notice-error"><p>Please configure Claude settings first before setting it as primary provider.</p></div>';
            return;
        }
        
        // LiteLLM proxy requires no additional configuration - ready to use out of the box
        
        // Update general settings
        $current_settings['api_provider'] = $primary_provider;
        $current_settings['conversation_retention'] = $conversation_retention;
        $current_settings['debug_mode'] = $debug_mode;
        
        // Set fallback model based on primary provider only if no model is set
        if (empty($current_settings['model'])) {
            if ($primary_provider === 'openai_direct' && !empty($current_settings['openai_model'])) {
                $current_settings['model'] = $current_settings['openai_model'];
                $current_settings['max_tokens'] = $current_settings['openai_max_tokens'] ?? 4000;
            } elseif ($primary_provider === 'claude_direct' && !empty($current_settings['claude_model'])) {
                $current_settings['model'] = $current_settings['claude_model'];
                $current_settings['max_tokens'] = $current_settings['claude_max_tokens'] ?? 4000;
            }
        }
        
        update_option('wp_claude_code_settings', $current_settings);
        echo '<div class="notice notice-success"><p>General settings saved successfully!</p></div>';
    }
    
    /**
     * Get provider configuration
     */
    private function get_provider_config($provider) {
        $configs = array(
            'openai' => array(
                'name' => 'OpenAI',
                'icon' => '🤖',
                'description' => 'Configure your OpenAI API settings for GPT models with vision support.',
                'key_prefix' => 'sk-',
                'key_placeholder' => 'sk-...',
                'console_url' => 'https://platform.openai.com/account/api-keys',
                'console_name' => 'OpenAI Platform',
                'default_model' => 'gpt-4o',
                'max_tokens' => 128000,
                'max_temperature' => 2,
                'models' => array(
                    'gpt-4o' => 'GPT-4o 🖼️ (Recommended)',
                    'gpt-4o-mini' => 'GPT-4o Mini 🖼️ (Faster)',
                    'gpt-4-turbo' => 'GPT-4 Turbo 🖼️',
                    'gpt-4' => 'GPT-4'
                ),
                'tokens_description' => 'Maximum tokens per response. Higher values cost more but allow longer responses.'
            ),
            'claude' => array(
                'name' => 'Claude',
                'icon' => '🧠',
                'description' => 'Configure your Anthropic Claude API settings for advanced reasoning and vision.',
                'key_prefix' => 'sk-ant-',
                'key_placeholder' => 'sk-ant-...',
                'console_url' => 'https://console.anthropic.com/',
                'console_name' => 'Anthropic Console',
                'default_model' => 'claude-3-5-sonnet-20241022',
                'max_tokens' => 200000,
                'max_temperature' => 1,
                'models' => array(
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet 🖼️ (Recommended)',
                    'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku 🖼️ (Faster)',
                    'claude-3-opus-20240229' => 'Claude 3 Opus 🖼️ (Most Capable)'
                ),
                'tokens_description' => 'Maximum tokens per response. Claude supports up to 200K tokens for large context.'
            )
        );
        
        return $configs[$provider] ?? null;
    }
    
    /**
     * Validate API key format
     */
    private function validate_api_key_format($api_key, $provider) {
        $config = $this->get_provider_config($provider);
        if (!$config) return false;
        
        return strpos($api_key, $config['key_prefix']) === 0 && strlen($api_key) > strlen($config['key_prefix']) + 5;
    }
    
    public function handle_chat_request() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $message = sanitize_textarea_field($_POST['message']);
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        $attachments = array_map('sanitize_text_field', $_POST['attachments'] ?? array());
        
        if (empty($conversation_id)) {
            $conversation_id = uniqid('conv_');
        }
        
        // Save user message
        $this->save_message($conversation_id, 'user', $message);
        
        // Process with Claude API
        $claude_api = new WP_Claude_Code_Claude_API();
        
        // Use specified model if provided
        if (!empty($model)) {
            $claude_api->set_model($model);
        }
        
        $response = $claude_api->send_message($message, $conversation_id, $attachments);
        
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
    
    public function handle_set_model() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $model = sanitize_text_field($_POST['model']);
        $user_id = get_current_user_id();
        
        // Get current settings to determine which provider to use for this model
        $settings = get_option('wp_claude_code_settings', array());
        $primary_provider = $settings['api_provider'] ?? 'litellm_proxy';
        
        // Determine the appropriate provider for the selected model
        $model_provider = $primary_provider; // Default to primary provider
        
        // For direct API providers, switch provider based on model type
        if ($primary_provider !== 'litellm_proxy') {
            if (strpos($model, 'claude') !== false) {
                $model_provider = 'claude_direct';
            } elseif (strpos($model, 'gpt') !== false) {
                $model_provider = 'openai_direct';
            }
            
            // Validate that we have the necessary API key for direct providers
            $api_key_key = ($model_provider === 'claude_direct') ? 'claude_api_key' : 'openai_api_key';
            if (empty($settings[$api_key_key])) {
                $provider_name = ($model_provider === 'claude_direct') ? 'Claude' : 'OpenAI';
                wp_send_json_error("$provider_name API key is required to use this model. Please configure it in the $provider_name settings tab.");
                return;
            }
        }
        // LiteLLM proxy handles all models without requiring API key validation
        
        // Save user's model preference
        update_user_meta($user_id, 'claude_code_preferred_model', $model);
        
        wp_send_json_success(array(
            'message' => 'Model preference saved',
            'model' => $model,
            'provider' => $model_provider
        ));
    }
    
    public function handle_get_user_model() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $user_id = get_current_user_id();
        $preferred_model = get_user_meta($user_id, 'claude_code_preferred_model', true);
        
        // If no preference set, use default from settings
        if (empty($preferred_model)) {
            $settings = get_option('wp_claude_code_settings', array());
            $preferred_model = $settings['model'] ?? 'claude-3-sonnet';
        }
        
        wp_send_json_success(array('model' => $preferred_model));
    }
    
    public function handle_debug_image() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $claude_api = new WP_Claude_Code_Claude_API();
        $settings = get_option('wp_claude_code_settings', array());
        
        // Get current model from settings or user preference
        $user_id = get_current_user_id();
        $user_model = get_user_meta($user_id, 'claude_code_preferred_model', true);
        $default_model = $settings['model'] ?? 'claude-3-sonnet';
        $current_model = $user_model ?: $default_model;
        
        // Check if model supports vision
        $vision_models = array(
            'claude-3-sonnet', 'claude-3-opus', 'claude-3-haiku', 
            'gpt-4o', 'gpt-4o-mini', 'gpt-4-vision-preview'
        );
        $supports_vision = in_array($current_model, $vision_models);
        
        $debug_info = array(
            'current_model' => $current_model,
            'model_supports_vision' => $supports_vision,
            'api_provider' => $settings['api_provider'] ?? 'litellm_proxy',
            'claude_api_key_configured' => !empty($settings['claude_api_key']),
            'openai_api_key_configured' => !empty($settings['openai_api_key']),
            'vision_models' => $vision_models
        );
        
        wp_send_json_success($debug_info);
    }
    
    
    /**
     * Handle provider-aware test connection
     */
    public function handle_test_connection() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $settings = get_option('wp_claude_code_settings', array());
        $provider = $settings['api_provider'] ?? 'litellm_proxy';
        
        $claude_api = new WP_Claude_Code_Claude_API();
        
        try {
            // Test a simple message based on provider
            $test_message = "Hello! This is a connection test. Please respond with 'Connection successful'.";
            $result = $claude_api->send_message($test_message);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array(
                    'message' => 'Connection failed: ' . $result->get_error_message(),
                    'provider' => $provider
                ));
            } else {
                wp_send_json_success(array(
                    'message' => 'Connection successful! Provider: ' . ucfirst($provider),
                    'response' => $result['content'] ?? 'No response content',
                    'provider' => $provider
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Connection error: ' . $e->getMessage(),
                'provider' => $provider
            ));
        }
    }
    
    /**
     * Handle AJAX request to get available models
     */
    public function handle_get_available_models() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $claude_api = new WP_Claude_Code_Claude_API();
        $models = $claude_api->get_available_models();
        
        if (is_wp_error($models)) {
            wp_send_json_error($models->get_error_message());
        }
        
        // Count total models across all categories
        $total_models = 0;
        if (isset($models['claude'])) $total_models += count($models['claude']);
        if (isset($models['openai'])) $total_models += count($models['openai']);
        if (isset($models['other'])) $total_models += count($models['other']);
        
        wp_send_json_success(array(
            'models' => $models,
            'total_models' => $total_models,
            'is_fallback' => false // Our method handles fallbacks internally
        ));
    }
    
    /**
     * Handle AJAX request to refresh models cache
     */
    public function handle_refresh_models() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $claude_api = new WP_Claude_Code_Claude_API();
        
        // Clear cache and get fresh models
        $claude_api->clear_models_cache();
        $models = $claude_api->get_available_models();
        
        if (is_wp_error($models)) {
            wp_send_json_error($models->get_error_message());
        }
        
        // Count total models across all categories
        $total_models = 0;
        if (isset($models['claude'])) $total_models += count($models['claude']);
        if (isset($models['openai'])) $total_models += count($models['openai']);
        if (isset($models['other'])) $total_models += count($models['other']);
        
        wp_send_json_success(array(
            'models' => $models,
            'total_models' => $total_models,
            'is_fallback' => false // Our method handles fallbacks internally
        ));
    }
    
    /**
     * Handle AJAX request to get current provider status
     */
    public function handle_get_provider_status() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }
        
        $settings = get_option('wp_claude_code_settings', array());
        $provider = $settings['api_provider'] ?? 'litellm_proxy';
        
        // Get additional provider information
        $provider_info = array(
            'provider' => $provider,
            'requires_api_key' => $provider !== 'litellm_proxy',
            'ready' => true
        );
        
        // Check if provider is properly configured
        if ($provider === 'claude_direct' && empty($settings['claude_api_key'])) {
            $provider_info['ready'] = false;
            $provider_info['error'] = 'Claude API key not configured';
        } elseif ($provider === 'openai_direct' && empty($settings['openai_api_key'])) {
            $provider_info['ready'] = false;
            $provider_info['error'] = 'OpenAI API key not configured';
        }
        
        wp_send_json_success($provider_info);
    }
    
    /**
     * Handle AJAX request to save provider settings
     */
    public function handle_save_provider_settings() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $settings = $_POST['settings'] ?? array();
        
        if (!in_array($provider, ['openai', 'claude'])) {
            wp_send_json_error('Invalid provider');
        }
        
        $current_settings = get_option('wp_claude_code_settings', array());
        
        try {
            $this->save_provider_settings_ajax($provider, $current_settings, $settings);
            
            wp_send_json_success(array(
                'message' => ucfirst($provider) . ' settings saved successfully!',
                'provider' => $provider
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle AJAX request to detect configuration
     */
    public function handle_detect_configuration() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        
        if (!in_array($provider, ['openai', 'claude'])) {
            wp_send_json_error('Invalid provider');
        }
        
        $detected_config = $this->detect_provider_configuration($provider);
        
        if ($detected_config) {
            wp_send_json_success(array(
                'config' => $detected_config,
                'message' => 'Configuration detected successfully!'
            ));
        } else {
            wp_send_json_error('No configuration detected for ' . $provider);
        }
    }
    
    /**
     * Handle provider-specific connection testing
     */
    public function handle_test_provider_connection() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');
        
        if (!in_array($provider, ['openai', 'claude'])) {
            wp_send_json_error('Invalid provider');
        }
        
        if (empty($api_key)) {
            wp_send_json_error('API key is required');
        }
        
        try {
            $result = $this->test_provider_connection_unified($provider, $api_key, $model, 'Hello! This is a connection test. Please respond with \'Connection successful\'.');
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => 'Connection successful!',
                    'response' => $result['response'],
                    'model_used' => $result['model'],
                    'provider' => $provider
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Connection failed: ' . $result['error'],
                    'provider' => $provider
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Connection error: ' . $e->getMessage(),
                'provider' => $provider
            ));
        }
    }
    
    /**
     * Save OpenAI settings via AJAX
     */
    /**
     * Save provider settings via AJAX (unified)
     */
    private function save_provider_settings_ajax($provider, $current_settings, $settings) {
        $provider_config = $this->get_provider_config($provider);
        
        $api_key = sanitize_text_field($settings['api_key'] ?? '');
        $model = sanitize_text_field($settings['model'] ?? $provider_config['default_model']);
        $max_tokens = absint($settings['max_tokens'] ?? 4000);
        $temperature = floatval($settings['temperature'] ?? 0.7);
        
        if (empty($api_key)) {
            throw new Exception($provider_config['name'] . ' API key is required');
        }
        
        if (!$this->validate_api_key_format($api_key, $provider)) {
            throw new Exception('Invalid ' . $provider_config['name'] . ' API key format');
        }
        
        $current_settings[$provider . '_api_key'] = $api_key;
        $current_settings[$provider . '_model'] = $model;
        $current_settings[$provider . '_max_tokens'] = max(100, min($provider_config['max_tokens'], $max_tokens));
        $current_settings[$provider . '_temperature'] = max(0, min($provider_config['max_temperature'], $temperature));
        
        update_option('wp_claude_code_settings', $current_settings);
    }
    
    /**
     * Detect provider configuration from environment or other sources
     */
    private function detect_provider_configuration($provider) {
        $config = array();
        
        if ($provider === 'openai') {
            // Check environment variables
            if (!empty($_ENV['OPENAI_API_KEY'])) {
                $config['api_key'] = $_ENV['OPENAI_API_KEY'];
            }
            // Check common WordPress constants
            if (defined('OPENAI_API_KEY')) {
                $config['api_key'] = OPENAI_API_KEY;
            }
            // Recommend default model
            $config['model'] = 'gpt-4o';
            $config['max_tokens'] = 4000;
            $config['temperature'] = 0.7;
        } elseif ($provider === 'claude') {
            // Check environment variables
            if (!empty($_ENV['CLAUDE_API_KEY']) || !empty($_ENV['ANTHROPIC_API_KEY'])) {
                $config['api_key'] = $_ENV['CLAUDE_API_KEY'] ?? $_ENV['ANTHROPIC_API_KEY'];
            }
            // Check common WordPress constants
            if (defined('CLAUDE_API_KEY')) {
                $config['api_key'] = CLAUDE_API_KEY;
            }
            if (defined('ANTHROPIC_API_KEY')) {
                $config['api_key'] = ANTHROPIC_API_KEY;
            }
            // Recommend default model
            $config['model'] = 'claude-3-5-sonnet-20241022';
            $config['max_tokens'] = 4000;
            $config['temperature'] = 0.7;
        }
        
        return !empty($config) ? $config : false;
    }
    
    
    /**
     * Test provider connection (unified)
     */
    private function test_provider_connection_unified($provider, $api_key, $model, $message) {
        if ($provider === 'openai') {
            return $this->test_openai_connection($api_key, $model, $message);
        } elseif ($provider === 'claude') {
            return $this->test_claude_connection($api_key, $model, $message);
        }
        
        return array('success' => false, 'error' => 'Invalid provider');
    }
    
    /**
     * Test OpenAI connection
     */
    private function test_openai_connection($api_key, $model, $message) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'user', 'content' => $message)
            ),
            'max_tokens' => 50,
            'temperature' => 0.1
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['error'])) {
            return array('success' => false, 'error' => $result['error']['message']);
        }
        
        if (isset($result['choices'][0]['message']['content'])) {
            return array(
                'success' => true,
                'response' => trim($result['choices'][0]['message']['content']),
                'model' => $model
            );
        }
        
        return array('success' => false, 'error' => 'Unexpected response format');
    }
    
    /**
     * Test Claude connection
     */
    private function test_claude_connection($api_key, $model, $message) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $data = array(
            'model' => $model,
            'max_tokens' => 50,
            'messages' => array(
                array('role' => 'user', 'content' => $message)
            )
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (isset($result['error'])) {
            return array('success' => false, 'error' => $result['error']['message']);
        }
        
        if (isset($result['content'][0]['text'])) {
            return array(
                'success' => true,
                'response' => trim($result['content'][0]['text']),
                'model' => $model
            );
        }
        
        return array('success' => false, 'error' => 'Unexpected response format');
    }
    
    /**
     * Migrate old settings structure to new tabbed structure
     */
    public function maybe_migrate_settings() {
        $settings = get_option('wp_claude_code_settings', array());
        $migration_version = get_option('wp_claude_code_migration_version', '1.0.0');
        
        // Check if migration is needed
        if (version_compare($migration_version, '2.0.0', '<')) {
            $this->migrate_to_tabbed_structure($settings);
            update_option('wp_claude_code_migration_version', '2.0.0');
        }
    }
    
    /**
     * Migrate settings to new tabbed structure
     */
    private function migrate_to_tabbed_structure($old_settings) {
        $new_settings = array();
        
        // Preserve existing settings
        $new_settings = array_merge($old_settings, $new_settings);
        
        // Migrate provider-specific settings - keep existing provider format
        if (!empty($old_settings['api_provider'])) {
            $new_settings['api_provider'] = $old_settings['api_provider'];
            
            // Migrate API keys to provider-specific keys
            if (($old_settings['api_provider'] === 'openai_direct' || $old_settings['api_provider'] === 'openai') && !empty($old_settings['openai_api_key'])) {
                $new_settings['openai_api_key'] = $old_settings['openai_api_key'];
                $new_settings['openai_model'] = $this->get_provider_model($old_settings['model'] ?? '', 'openai');
                $new_settings['openai_max_tokens'] = $old_settings['max_tokens'] ?? 4000;
                $new_settings['openai_temperature'] = 0.7; // Default value
            }
            
            if (($old_settings['api_provider'] === 'claude_direct' || $old_settings['api_provider'] === 'claude') && !empty($old_settings['claude_api_key'])) {
                $new_settings['claude_api_key'] = $old_settings['claude_api_key'];
                $new_settings['claude_model'] = $this->get_provider_model($old_settings['model'] ?? '', 'claude');
                $new_settings['claude_max_tokens'] = $old_settings['max_tokens'] ?? 4000;
                $new_settings['claude_temperature'] = 0.7; // Default value
            }
        }
        
        // Set defaults for new settings if not present
        if (!isset($new_settings['conversation_retention'])) {
            $new_settings['conversation_retention'] = 30;
        }
        
        if (!isset($new_settings['debug_mode'])) {
            $new_settings['debug_mode'] = false;
        }
        
        // Ensure enabled_tools is set
        if (!isset($new_settings['enabled_tools'])) {
            $new_settings['enabled_tools'] = array(
                'file_read',
                'file_edit', 
                'wp_cli',
                'db_query',
                'plugin_repository',
                'content_management'
            );
        }
        
        update_option('wp_claude_code_settings', $new_settings);
    }
    
    /**
     * Get appropriate model for provider
     */
    private function get_provider_model($old_model, $provider) {
        $config = $this->get_provider_config($provider);
        if (!$config) return '';
        
        $available_models = array_keys($config['models']);
        
        if (in_array($old_model, $available_models)) {
            return $old_model;
        }
        
        // Default to recommended model
        return $config['default_model'];
    }
    
    /**
     * Render provider header section
     */
    private function render_provider_header($provider, $config, $settings) {
        $is_configured = !empty($settings[$provider . '_api_key']);
        ?>
        <div class="provider-header">
            <div class="provider-info">
                <h2><?php echo $config['icon']; ?> <?php echo esc_html($config['name']); ?> Configuration</h2>
                <p class="description"><?php echo esc_html($config['description']); ?></p>
            </div>
            <div class="provider-status">
                <div class="status-indicator" id="<?php echo esc_attr($provider); ?>-status">
                    <span class="status-dot <?php echo $is_configured ? 'connected' : ''; ?>"></span>
                    <span class="status-text"><?php echo $is_configured ? 'Configured' : 'Not Configured'; ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render API key input field
     */
    private function render_api_key_field($provider, $config, $settings) {
        $field_id = $provider . '_api_key';
        $field_name = $provider . '_api_key';
        $field_value = $settings[$field_name] ?? '';
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>"><?php echo esc_html($config['name']); ?> API Key</label>
            </th>
            <td>
                <div class="api-key-input-wrapper">
                    <input type="password" 
                           id="<?php echo esc_attr($field_id); ?>"
                           name="<?php echo esc_attr($field_name); ?>" 
                           value="<?php echo esc_attr($field_value); ?>" 
                           class="regular-text api-key-input" 
                           placeholder="<?php echo esc_attr($config['key_placeholder']); ?>" 
                           autocomplete="off" />
                    <button type="button" class="button button-secondary toggle-visibility" data-target="<?php echo esc_attr($field_id); ?>">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </div>
                <p class="description">
                    Get your API key from <a href="<?php echo esc_url($config['console_url']); ?>" target="_blank"><?php echo esc_html($config['console_name']); ?></a>. 
                    <button type="button" class="button-link detect-config" data-provider="<?php echo esc_attr($provider); ?>">Auto-detect</button>
                </p>
                <div class="api-key-validation" id="<?php echo esc_attr($provider); ?>-key-validation" style="display: none;"></div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render model selector field
     */
    private function render_model_selector_field($provider, $config, $settings) {
        $field_id = $provider . '_model';
        $field_name = $provider . '_model';
        $field_value = $settings[$field_name] ?? $config['default_model'];
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>">Default Model</label>
            </th>
            <td>
                <select name="<?php echo esc_attr($field_name); ?>" id="<?php echo esc_attr($field_id); ?>" class="model-selector">
                    <?php foreach ($config['models'] as $model_id => $model_name): ?>
                        <option value="<?php echo esc_attr($model_id); ?>" <?php selected($field_value, $model_id); ?>>
                            <?php echo esc_html($model_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="refresh-<?php echo esc_attr($provider); ?>-models" class="button button-small">🔄</button>
                <p class="description">🖼️ = Supports image analysis. <span id="<?php echo esc_attr($provider); ?>-models-status"></span></p>
                <div class="model-recommendation" id="<?php echo esc_attr($provider); ?>-model-recommendation"></div>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render max tokens field
     */
    private function render_max_tokens_field($provider, $config, $settings) {
        $field_id = $provider . '_max_tokens';
        $field_name = $provider . '_max_tokens';
        $field_value = $settings[$field_name] ?? 4000;
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>">Max Tokens</label>
            </th>
            <td>
                <input type="number" 
                       id="<?php echo esc_attr($field_id); ?>"
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($field_value); ?>" 
                       min="100" 
                       max="<?php echo esc_attr($config['max_tokens']); ?>" 
                       class="small-text" />
                <p class="description"><?php echo esc_html($config['tokens_description']); ?></p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render temperature field
     */
    private function render_temperature_field($provider, $config, $settings) {
        $field_id = $provider . '_temperature';
        $field_name = $provider . '_temperature';
        $field_value = $settings[$field_name] ?? 0.7;
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>">Temperature</label>
            </th>
            <td>
                <input type="range" 
                       id="<?php echo esc_attr($field_id); ?>"
                       name="<?php echo esc_attr($field_name); ?>" 
                       value="<?php echo esc_attr($field_value); ?>" 
                       min="0" 
                       max="<?php echo esc_attr($config['max_temperature']); ?>" 
                       step="0.1" 
                       class="temperature-slider" />
                <span class="temperature-value"><?php echo esc_html($field_value); ?></span>
                <p class="description">Controls randomness. Lower = more focused, Higher = more creative.</p>
            </td>
        </tr>
        <?php
    }
    
    /**
     * Render provider actions section
     */
    private function render_provider_actions($provider, $config) {
        ?>
        <div class="provider-actions">
            <button type="button" class="button test-connection" data-provider="<?php echo esc_attr($provider); ?>">Test Connection</button>
            <?php submit_button('Save ' . $config['name'] . ' Settings', 'primary', 'submit', false); ?>
        </div>
        <?php
    }
    
    /**
     * Render enabled tools section
     */
    private function render_enabled_tools_section($settings) {
        $enabled_tools = $settings['enabled_tools'] ?? array();
        $available_tools = array(
            'file_read' => array(
                'label' => 'File Reading',
                'description' => 'Allow AI to read WordPress files and themes'
            ),
            'file_edit' => array(
                'label' => 'File Editing', 
                'description' => 'Allow AI to modify WordPress files (use with caution)'
            ),
            'wp_cli' => array(
                'label' => 'WP-CLI Commands',
                'description' => 'Execute WordPress CLI commands'
            ),
            'db_query' => array(
                'label' => 'Database Queries',
                'description' => 'Read and query WordPress database'
            ),
            'plugin_repository' => array(
                'label' => 'Plugin Repository Check',
                'description' => 'Search WordPress.org plugin repository'
            ),
            'content_management' => array(
                'label' => 'Content Management',
                'description' => 'Create and manage posts, pages, and media'
            ),
        );

        foreach ($available_tools as $tool => $info) {
            $checked = in_array($tool, $enabled_tools) ? 'checked' : '';
            echo '<label class="tool-checkbox">';
            echo "<input type='checkbox' name='enabled_tools[]' value='$tool' $checked> ";
            echo "<strong>{$info['label']}</strong>";
            echo "<br><span class='description'>{$info['description']}</span>";
            echo '</label><br><br>';
        }
    }
    
    /**
     * Render chat UI section
     */
    private function render_chat_ui_section($settings) {
        ?>
        <label>
            <input type="checkbox"
                   name="chat_ui_enabled"
                   value="1"
                   <?php checked(!empty($settings['chat_ui_enabled'])); ?> />
            Enable Modern Chat UI
        </label>
        <p class="description">Provides markdown rendering, syntax highlighting, and WhatsApp-style message bubbles</p>

        <div style="margin-top: 10px;">
            <label for="chat_ui_renderer">Markdown Rendering:</label>
            <select name="chat_ui_renderer" id="chat_ui_renderer">
                <option value="client" <?php selected($settings['chat_ui_renderer'] ?? 'client', 'client'); ?>>Client-side (faster)</option>
                <option value="server" <?php selected($settings['chat_ui_renderer'] ?? 'client', 'server'); ?>>Server-side (more secure)</option>
            </select>
        </div>
        <?php
    }
    
    /**
     * Get settings without forcing provider-specific models
     */
    public function get_settings() {
        $settings = get_option('wp_claude_code_settings', array());
        
        // Only set fallback model if no global model is set, don't override user preferences
        if (empty($settings['model']) && !empty($settings['api_provider'])) {
            $provider = $settings['api_provider'];
            
            if ($provider === 'openai_direct' && !empty($settings['openai_model'])) {
                $settings['model'] = $settings['openai_model'];
                $settings['max_tokens'] = $settings['openai_max_tokens'] ?? 4000;
            } elseif ($provider === 'claude_direct' && !empty($settings['claude_model'])) {
                $settings['model'] = $settings['claude_model'];
                $settings['max_tokens'] = $settings['claude_max_tokens'] ?? 4000;
            }
        }
        
        return $settings;
    }
    
    /**
     * Handle debug logs AJAX requests
     */
    public function handle_debug_logs() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $debug_action = $_POST['debug_action'] ?? '';
        
        switch ($debug_action) {
            case 'get_logs':
                $this->handle_get_debug_logs();
                break;
            case 'clear_logs':
                $this->handle_clear_debug_logs();
                break;
            case 'download_logs':
                $this->handle_download_debug_logs();
                break;
            default:
                wp_send_json_error('Invalid debug action');
        }
    }
    
    /**
     * Handle get debug logs AJAX request
     */
    private function handle_get_debug_logs() {
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 50);
        $filter = sanitize_text_field($_POST['filter'] ?? '');
        
        $logs = $this->read_debug_log_files($page, $per_page, $filter);
        
        wp_send_json_success($logs);
    }
    
    /**
     * Handle clear debug logs AJAX request
     */
    private function handle_clear_debug_logs() {
        $cleared = $this->clear_debug_log_files();
        
        if ($cleared) {
            $message = 'Debug logs cleared successfully';
            
            // Add helpful message if debug logging is disabled
            if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
                $message .= '. Note: Debug logging is currently disabled in WordPress.';
            }
            
            wp_send_json_success(array('message' => $message));
        } else {
            wp_send_json_error(array('message' => 'Failed to clear debug logs. Check file permissions.'));
        }
    }
    
    /**
     * Handle download debug logs AJAX request
     */
    private function handle_download_debug_logs() {
        $logs = $this->get_all_debug_log_content();
        
        // Always provide content, even if it's just instructions
        if (!empty($logs)) {
            wp_send_json_success(array('content' => $logs));
        } else {
            // This shouldn't happen with our new implementation, but just in case
            $fallback_content = "=== WP Claude Code Debug Information ===\n";
            $fallback_content .= "No debug logs available.\n\n";
            $fallback_content .= "Debug logging may be disabled or no logs exist yet.\n";
            $fallback_content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
            
            wp_send_json_success(array('content' => $fallback_content));
        }
    }
    
    /**
     * Read debug log files with pagination and filtering
     */
    private function read_debug_log_files($page = 1, $per_page = 50, $filter = '') {
        $log_entries = array();
        $total_entries = 0;
        
        // Possible log file locations
        $log_files = array();
        
        // WordPress debug.log (check if WP_DEBUG_LOG is configured)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (is_string(WP_DEBUG_LOG)) {
                $log_files[] = WP_DEBUG_LOG;
            } else {
                $log_files[] = WP_CONTENT_DIR . '/debug.log';
            }
        }
        
        // Always check common locations even if WP_DEBUG_LOG is not set
        $additional_logs = array(
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'wp-content/debug.log',
            ini_get('error_log'),
            '/var/log/apache2/error.log',
            '/var/log/nginx/error.log',
            '/var/log/php_errors.log'
        );
        
        foreach ($additional_logs as $log_file) {
            if ($log_file && file_exists($log_file) && is_readable($log_file)) {
                $log_files[] = $log_file;
            }
        }
        
        // Remove duplicates
        $log_files = array_unique($log_files);
        
        foreach ($log_files as $log_file) {
            if (!file_exists($log_file) || !is_readable($log_file)) {
                continue;
            }
            
            $file_size = filesize($log_file);
            if ($file_size === 0) {
                continue;
            }
            
            // Read file in reverse order for recent entries first
            $lines = $this->read_log_file_reverse($log_file, 1000); // Last 1000 lines
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Filter for plugin-related entries
                if (!empty($filter)) {
                    if (stripos($line, $filter) === false) {
                        continue;
                    }
                } elseif (stripos($line, 'WP Claude Code') === false && 
                          stripos($line, 'claude-code') === false && 
                          stripos($line, 'wp_claude_code') === false) {
                    continue;
                }
                
                $parsed = $this->parse_log_entry($line, basename($log_file));
                if ($parsed) {
                    $log_entries[] = $parsed;
                    $total_entries++;
                }
            }
        }
        
        // Sort by timestamp (newest first)
        usort($log_entries, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Pagination
        $offset = ($page - 1) * $per_page;
        $paged_entries = array_slice($log_entries, $offset, $per_page);
        
        return array(
            'entries' => $paged_entries,
            'total' => $total_entries,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_entries / $per_page),
            'log_files' => array_map('basename', $log_files)
        );
    }
    
    /**
     * Read log file in reverse order
     */
    private function read_log_file_reverse($file, $max_lines = 1000) {
        if (!file_exists($file) || !is_readable($file)) {
            return array();
        }
        
        $file_size = filesize($file);
        if ($file_size === 0) {
            return array();
        }
        
        // For small files, just read normally
        if ($file_size < 1024 * 1024) { // Less than 1MB
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return array_reverse(array_slice($lines, -$max_lines));
        }
        
        // For large files, read from the end
        $lines = array();
        $fp = fopen($file, 'r');
        
        if (!$fp) {
            return array();
        }
        
        fseek($fp, -1, SEEK_END);
        $pos = ftell($fp);
        $line = '';
        
        while ($pos >= 0 && count($lines) < $max_lines) {
            $char = fgetc($fp);
            if ($char === "\n" || $pos === 0) {
                if (!empty(trim($line))) {
                    $lines[] = $line;
                }
                $line = '';
            } else {
                $line = $char . $line;
            }
            fseek($fp, --$pos);
        }
        
        fclose($fp);
        return $lines;
    }
    
    /**
     * Parse log entry into structured format
     */
    private function parse_log_entry($line, $source = '') {
        // Try to parse common log formats
        $patterns = array(
            // Standard PHP error log format
            '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2} \w+)\] (.+)$/',
            // WordPress debug.log format
            '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2})\] (.+)$/',
            // Apache error log format
            '/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/',
            // Simple timestamp format
            '/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - (.+)$/',
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return array(
                    'timestamp' => $matches[1],
                    'message' => isset($matches[3]) ? $matches[3] : $matches[2],
                    'level' => $this->determine_log_level($line),
                    'source' => $source,
                    'raw' => $line
                );
            }
        }
        
        // If no pattern matches, return basic info
        return array(
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $line,
            'level' => $this->determine_log_level($line),
            'source' => $source,
            'raw' => $line
        );
    }
    
    /**
     * Determine log level from message content
     */
    private function determine_log_level($line) {
        $line_lower = strtolower($line);
        
        if (strpos($line_lower, 'error') !== false || strpos($line_lower, 'fatal') !== false) {
            return 'error';
        } elseif (strpos($line_lower, 'warning') !== false) {
            return 'warning';
        } elseif (strpos($line_lower, 'notice') !== false) {
            return 'notice';
        } else {
            return 'info';
        }
    }
    
    /**
     * Clear debug log files
     */
    private function clear_debug_log_files() {
        $cleared = false;
        $cleared_files = array();
        
        // Try common debug log locations
        $potential_logs = array();
        
        // WordPress debug.log (check if WP_DEBUG_LOG is configured)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $potential_logs[] = is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
        }
        
        // Add common debug log locations even if WP_DEBUG_LOG is not set
        $potential_logs[] = WP_CONTENT_DIR . '/debug.log';
        $potential_logs[] = ABSPATH . 'wp-content/debug.log';
        
        // Also check for error logs that might contain plugin entries
        $error_log = ini_get('error_log');
        if ($error_log && file_exists($error_log)) {
            $potential_logs[] = $error_log;
        }
        
        // Try to clear any existing debug logs
        foreach (array_unique($potential_logs) as $log_file) {
            if (file_exists($log_file) && is_writable($log_file)) {
                $file_size = filesize($log_file);
                if ($file_size > 0) {
                    file_put_contents($log_file, '');
                    $cleared = true;
                    $cleared_files[] = basename($log_file);
                }
            }
        }
        
        // If no files were cleared but debug logging is disabled, still return true
        // with a message that debug logging should be enabled
        if (!$cleared && (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG)) {
            // Create a dummy success since there's nothing to clear when debug is disabled
            $cleared = true;
        }
        
        return $cleared;
    }
    
    /**
     * Get all debug log content for download
     */
    private function get_all_debug_log_content() {
        $all_content = "";
        $found_logs = false;
        
        // Try common debug log locations
        $potential_logs = array();
        
        // WordPress debug.log (check if WP_DEBUG_LOG is configured)
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $potential_logs[] = is_string(WP_DEBUG_LOG) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/debug.log';
        }
        
        // Add common debug log locations even if WP_DEBUG_LOG is not set
        $potential_logs[] = WP_CONTENT_DIR . '/debug.log';
        $potential_logs[] = ABSPATH . 'wp-content/debug.log';
        
        // Try to read from any existing debug logs
        foreach (array_unique($potential_logs) as $log_file) {
            if (file_exists($log_file) && is_readable($log_file) && filesize($log_file) > 0) {
                $content = file_get_contents($log_file);
                if ($content) {
                    $found_logs = true;
                    // Filter for plugin-related entries
                    $lines = explode("\n", $content);
                    $filtered_lines = array();
                    
                    foreach ($lines as $line) {
                        if (stripos($line, 'WP Claude Code') !== false || 
                            stripos($line, 'claude-code') !== false || 
                            stripos($line, 'wp_claude_code') !== false) {
                            $filtered_lines[] = $line;
                        }
                    }
                    
                    if (!empty($filtered_lines)) {
                        $all_content .= "=== " . basename($log_file) . " ===\n";
                        $all_content .= implode("\n", $filtered_lines) . "\n\n";
                    }
                }
            }
        }
        
        // If no logs found and debug logging is disabled, provide helpful info
        if (!$found_logs && (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG)) {
            $all_content = "=== WP Claude Code Debug Information ===\n";
            $all_content .= "No debug logs found.\n\n";
            $all_content .= "Debug logging is currently disabled in WordPress.\n";
            $all_content .= "To enable debug logging, add these lines to your wp-config.php file:\n\n";
            $all_content .= "define('WP_DEBUG', true);\n";
            $all_content .= "define('WP_DEBUG_LOG', true);\n";
            $all_content .= "define('WP_DEBUG_DISPLAY', false);\n\n";
            $all_content .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        }
        
        return $all_content;
    }
}