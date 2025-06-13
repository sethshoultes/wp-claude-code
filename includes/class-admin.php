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
        add_action('wp_ajax_claude_code_test_image_format', array($this, 'handle_test_image_format'));
        add_action('wp_ajax_claude_code_test_litellm_direct', array($this, 'handle_test_litellm_direct'));
        add_action('wp_ajax_claude_code_test_connection', array($this, 'handle_test_connection'));
        add_action('wp_ajax_claude_code_get_available_models', array($this, 'handle_get_available_models'));
        add_action('wp_ajax_claude_code_refresh_models', array($this, 'handle_refresh_models'));
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
                        <div class="header-left">
                            <h3>WordPress Development Assistant</h3>
                            <div class="model-selector">
                                <select id="model-selector">
                                    <option value="claude-3-sonnet">Claude 3 Sonnet 🖼️</option>
                                    <option value="claude-3-opus">Claude 3 Opus 🖼️</option>
                                    <option value="claude-3-haiku">Claude 3 Haiku 🖼️</option>
                                    <option value="gpt-4o">GPT-4o 🖼️</option>
                                    <option value="gpt-4o-mini">GPT-4o Mini 🖼️</option>
                                    <option value="gpt-4">GPT-4</option>
                                    <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                </select>
                                <button id="refresh-models" class="button button-small" title="Refresh available models from LiteLLM proxy">🔄</button>
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
                                <li>Creating staging environments</li>
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
                            <div class="tool-item" data-tool="staging">
                                <span class="tool-icon">🚀</span>
                                <span class="tool-name">Staging</span>
                                <span class="tool-status-dot"></span>
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
        
        $settings = get_option('wp_claude_code_settings', array());
        ?>
        <div class="wrap">
            <h1>Claude Code Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('claude_code_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">API Provider</th>
                        <td>
                            <select name="api_provider" id="api_provider">
                                <option value="litellm" <?php selected($settings['api_provider'] ?? 'litellm', 'litellm'); ?>>
                                    LiteLLM Proxy (Multi-model support)
                                </option>
                                <option value="claude_direct" <?php selected($settings['api_provider'] ?? 'litellm', 'claude_direct'); ?>>
                                    Direct Claude API (Best for image analysis)
                                </option>
                                <option value="openai_direct" <?php selected($settings['api_provider'] ?? 'litellm', 'openai_direct'); ?>>
                                    Direct OpenAI API (GPT-4o with vision)
                                </option>
                            </select>
                            <p class="description">Choose your preferred API provider. Direct APIs offer better image support and reliability.</p>
                        </td>
                    </tr>
                    
                    <tr id="litellm_endpoint_section">
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
                    
                    <tr id="api_key_section">
                        <th scope="row">LiteLLM API Key</th>
                        <td>
                            <input type="password" 
                                   name="api_key" 
                                   value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>" 
                                   class="regular-text" />
                            <p class="description">API key for your LiteLLM proxy (if required)</p>
                        </td>
                    </tr>
                    
                    <tr id="claude_api_key_section" style="display: none;">
                        <th scope="row">Claude API Key</th>
                        <td>
                            <input type="password" 
                                   name="claude_api_key" 
                                   value="<?php echo esc_attr($settings['claude_api_key'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="sk-ant-..." />
                            <p class="description">Your Anthropic Claude API key (get from console.anthropic.com)</p>
                        </td>
                    </tr>
                    
                    <tr id="openai_api_key_section" style="display: none;">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" 
                                   name="openai_api_key" 
                                   value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="sk-..." />
                            <p class="description">Your OpenAI API key (get from platform.openai.com)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Default Model</th>
                        <td>
                            <select name="model" id="model_selector">
                                <optgroup label="Claude Models (Vision Support)">
                                    <option value="claude-3-sonnet" <?php selected($settings['model'] ?? '', 'claude-3-sonnet'); ?>>Claude 3 Sonnet 🖼️</option>
                                    <option value="claude-3-opus" <?php selected($settings['model'] ?? '', 'claude-3-opus'); ?>>Claude 3 Opus 🖼️</option>
                                    <option value="claude-3-haiku" <?php selected($settings['model'] ?? '', 'claude-3-haiku'); ?>>Claude 3 Haiku 🖼️</option>
                                </optgroup>
                                <optgroup label="OpenAI Models (Vision Support)">
                                    <option value="gpt-4o" <?php selected($settings['model'] ?? '', 'gpt-4o'); ?>>GPT-4o 🖼️</option>
                                    <option value="gpt-4o-mini" <?php selected($settings['model'] ?? '', 'gpt-4o-mini'); ?>>GPT-4o Mini 🖼️</option>
                                </optgroup>
                                <optgroup label="Text-Only Models">
                                    <option value="gpt-4" <?php selected($settings['model'] ?? '', 'gpt-4'); ?>>GPT-4</option>
                                    <option value="gpt-3.5-turbo" <?php selected($settings['model'] ?? '', 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                </optgroup>
                            </select>
                            <button type="button" id="refresh-models-settings" class="button" style="margin-left: 10px;">Refresh Models</button>
                            <p class="description">Default model to use. Users can switch models in the chat interface. 🖼️ = supports image analysis<br>
                            <span id="models-status">Click "Refresh Models" to load available models from your LiteLLM proxy</span></p>
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
                                'plugin_repository' => 'WordPress.org Plugin Repository Check',
                                'content_management' => 'Content Management (Posts/Pages)',
                                'staging' => 'Staging Management'
                            );

                            foreach ($available_tools as $tool => $label) {
                                $checked = in_array($tool, $enabled_tools) ? 'checked' : '';
                                echo "<label><input type='checkbox' name='enabled_tools[]' value='$tool' $checked> $label</label><br>";
                            }
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Image Processing</th>
                        <td>
                            <label for="image_format_override">Force Image Format:</label>
                            <select name="image_format_override" id="image_format_override">
                                <option value="auto" <?php selected($settings['image_format_override'] ?? 'auto', 'auto'); ?>>Auto-detect (Recommended)</option>
                                <option value="openai" <?php selected($settings['image_format_override'] ?? 'auto', 'openai'); ?>>OpenAI format (with URLs)</option>
                                <option value="openai_base64_only" <?php selected($settings['image_format_override'] ?? 'auto', 'openai_base64_only'); ?>>OpenAI Base64 Only (LiteLLM Fix)</option>
                                <option value="claude" <?php selected($settings['image_format_override'] ?? 'auto', 'claude'); ?>>Claude format</option>
                            </select>
                            <p class="description">Override automatic format detection if you experience image processing issues with your LiteLLM configuration. Use "OpenAI Base64 Only" for custom LiteLLM setups (like .nip.io domains) that reject URL-based images.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Chat UI</th>
                        <td>
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
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="test-connection">
                <h3>Test Connection</h3>
                <button id="test-connection" class="button">Test API Connection</button>
                <div id="connection-result"></div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Handle API provider selection
                function toggleProviderSections() {
                    const provider = $('#api_provider').val();
                    
                    // Hide all provider-specific sections
                    $('#litellm_endpoint_section, #api_key_section, #claude_api_key_section, #openai_api_key_section').hide();
                    
                    // Show relevant sections and filter models based on provider
                    if (provider === 'litellm') {
                        $('#litellm_endpoint_section, #api_key_section').show();
                        // LiteLLM supports all models
                        $('#model_selector optgroup, #model_selector option').show();
                    } else if (provider === 'claude_direct') {
                        $('#claude_api_key_section').show();
                        // Only show Claude models
                        $('#model_selector optgroup').hide();
                        $('#model_selector optgroup').first().show(); // Claude models
                        $('#model_selector option').hide();
                        $('#model_selector optgroup').first().find('option').show();
                    } else if (provider === 'openai_direct') {
                        $('#openai_api_key_section').show();
                        // Only show OpenAI models
                        $('#model_selector optgroup').hide();
                        $('#model_selector optgroup').eq(1).show(); // OpenAI vision models
                        $('#model_selector optgroup').eq(2).show(); // Text-only models
                        $('#model_selector option').hide();
                        $('#model_selector optgroup').eq(1).find('option').show();
                        $('#model_selector optgroup').eq(2).find('option').show();
                    }
                }
                
                // All sections now have proper IDs in PHP, no need to add them dynamically
                
                // Initial toggle
                toggleProviderSections();
                
                // Handle provider changes
                $('#api_provider').on('change', toggleProviderSections);
                
                // Handle model refresh
                $('#refresh-models-settings').on('click', function() {
                    const button = $(this);
                    const status = $('#models-status');
                    
                    button.prop('disabled', true).text('Refreshing...');
                    status.html('Checking LiteLLM proxy for available models...');
                    
                    $.ajax({
                        url: claudeCode.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'claude_code_refresh_models',
                            nonce: claudeCode.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                updateModelSelector(response.data.models, '#model_selector');
                                status.html('<span style="color: #46b450;">✅ Models updated! Found ' + response.data.total_models + ' models from LiteLLM proxy</span>');
                                
                                if (response.data.is_fallback) {
                                    status.append('<br><span style="color: #ffb900;">⚠️ Using fallback models - unable to connect to LiteLLM proxy</span>');
                                }
                            } else {
                                status.html('<span style="color: #dc3232;">❌ Failed to refresh models: ' + (response.data || 'Unknown error') + '</span>');
                            }
                        },
                        error: function() {
                            status.html('<span style="color: #dc3232;">❌ Network error occurred while refreshing models</span>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Refresh Models');
                        }
                    });
                });
                
                // Auto-load models on page load if LiteLLM endpoint is configured
                const endpoint = $('input[name="litellm_endpoint"]').val();
                if (endpoint && endpoint.trim()) {
                    setTimeout(() => {
                        $('#refresh-models-settings').click();
                    }, 500);
                }
                
                // Provide instant feedback for image format override setting
                $('#image_format_override').on('change', function() {
                    const value = $(this).val();
                    const description = $(this).closest('td').find('.description');
                    
                    let helpText = 'Override automatic format detection if you experience image processing issues with your LiteLLM configuration';
                    
                    if (value === 'openai') {
                        helpText += '<br><strong>OpenAI format (with URLs):</strong> Uses image URLs when available, falls back to base64. Best for standard LiteLLM setups.';
                    } else if (value === 'openai_base64_only') {
                        helpText += '<br><strong>OpenAI Base64 Only (LiteLLM Fix):</strong> Forces base64 encoding only, bypasses URLs entirely. Use for custom LiteLLM setups (.nip.io domains, tunnels) that reject URL-based images.';
                    } else if (value === 'claude') {
                        helpText += '<br><strong>Claude format:</strong> Use if your LiteLLM proxy is specifically configured for native Anthropic API';
                    } else {
                        helpText += '<br><strong>Auto-detect:</strong> Automatically chooses format and detects problematic setups (recommended)';
                    }
                    
                    description.html(helpText);
                });
                
                // Helper function to update model selectors
                function updateModelSelector(models, selectorId) {
                    const selector = $(selectorId);
                    const currentValue = selector.val();
                    
                    // Clear existing options
                    selector.empty();
                    
                    // Add Claude models
                    if (models.claude && models.claude.length > 0) {
                        const claudeGroup = $('<optgroup label="Claude Models (Vision Support)"></optgroup>');
                        models.claude.forEach(function(model) {
                            const icon = model.supports_vision ? ' 🖼️' : '';
                            claudeGroup.append(`<option value="${model.id}">${model.name}${icon}</option>`);
                        });
                        selector.append(claudeGroup);
                    }
                    
                    // Add OpenAI models
                    if (models.openai && models.openai.length > 0) {
                        const openaiGroup = $('<optgroup label="OpenAI Models"></optgroup>');
                        models.openai.forEach(function(model) {
                            const icon = model.supports_vision ? ' 🖼️' : '';
                            const label = model.supports_vision ? 'OpenAI Models (Vision Support)' : 'OpenAI Models (Text Only)';
                            openaiGroup.attr('label', label);
                            openaiGroup.append(`<option value="${model.id}">${model.name}${icon}</option>`);
                        });
                        selector.append(openaiGroup);
                    }
                    
                    // Add other models
                    if (models.other && models.other.length > 0) {
                        const otherGroup = $('<optgroup label="Other Models"></optgroup>');
                        models.other.forEach(function(model) {
                            const icon = model.supports_vision ? ' 🖼️' : '';
                            otherGroup.append(`<option value="${model.id}">${model.name}${icon}</option>`);
                        });
                        selector.append(otherGroup);
                    }
                    
                    // Restore previous selection if it still exists
                    if (currentValue && selector.find(`option[value="${currentValue}"]`).length > 0) {
                        selector.val(currentValue);
                    }
                }
            });
            </script>
        </div>
        <?php
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'claude_code_settings_nonce')) {
            wp_die('Security check failed');
        }

        $api_provider = sanitize_text_field($_POST['api_provider'] ?? 'litellm');
        $model = sanitize_text_field($_POST['model']);
        
        // Validate provider-specific requirements
        if ($api_provider === 'claude_direct' && empty($_POST['claude_api_key'])) {
            echo '<div class="notice notice-error"><p>Claude API key is required when using Direct Claude API.</p></div>';
            return;
        }
        
        if ($api_provider === 'openai_direct' && empty($_POST['openai_api_key'])) {
            echo '<div class="notice notice-error"><p>OpenAI API key is required when using Direct OpenAI API.</p></div>';
            return;
        }
        
        if ($api_provider === 'litellm' && empty($_POST['litellm_endpoint'])) {
            echo '<div class="notice notice-error"><p>LiteLLM endpoint is required when using LiteLLM proxy.</p></div>';
            return;
        }
        
        // Validate model-provider compatibility
        if ($api_provider === 'claude_direct' && strpos($model, 'claude') === false) {
            echo '<div class="notice notice-error"><p>Claude models are required when using Direct Claude API. Please select a Claude model.</p></div>';
            return;
        }
        
        if ($api_provider === 'openai_direct' && strpos($model, 'gpt') === false) {
            echo '<div class="notice notice-error"><p>OpenAI models (GPT) are required when using Direct OpenAI API. Please select a GPT model.</p></div>';
            return;
        }

        $settings = array(
            'api_provider' => $api_provider,
            'litellm_endpoint' => sanitize_url($_POST['litellm_endpoint']),
            'api_key' => sanitize_text_field($_POST['api_key']),
            'claude_api_key' => sanitize_text_field($_POST['claude_api_key']),
            'openai_api_key' => sanitize_text_field($_POST['openai_api_key']),
            'model' => $model,
            'max_tokens' => absint($_POST['max_tokens']),
            'enabled_tools' => array_map('sanitize_text_field', $_POST['enabled_tools'] ?? array()),
            'image_format_override' => sanitize_text_field($_POST['image_format_override'] ?? 'auto'),
            'chat_ui_enabled' => !empty($_POST['chat_ui_enabled']),
            'chat_ui_renderer' => sanitize_text_field($_POST['chat_ui_renderer'] ?? 'client')
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
        
        // Check provider compatibility
        $settings = get_option('wp_claude_code_settings', array());
        $provider = $settings['api_provider'] ?? 'litellm';
        
        // Validate model-provider compatibility unless using LiteLLM (which supports all models)
        if ($provider === 'claude_direct' && strpos($model, 'claude') === false) {
            wp_send_json_error('Claude models are required when using Direct Claude API. Current provider: ' . $provider);
            return;
        }
        
        if ($provider === 'openai_direct' && strpos($model, 'gpt') === false) {
            wp_send_json_error('OpenAI models (GPT) are required when using Direct OpenAI API. Current provider: ' . $provider);
            return;
        }
        
        // Save user's model preference
        update_user_meta($user_id, 'claude_code_preferred_model', $model);
        
        wp_send_json_success(array(
            'message' => 'Model preference saved',
            'model' => $model,
            'provider' => $provider
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
            'image_format_override' => $settings['image_format_override'] ?? 'auto',
            'litellm_endpoint' => $settings['litellm_endpoint'] ?? 'not set',
            'api_key_configured' => !empty($settings['api_key']),
            'vision_models' => $vision_models,
            'endpoint_suggests_litellm' => (strpos($settings['litellm_endpoint'] ?? '', 'anthropic') === false && 
                                          strpos($settings['litellm_endpoint'] ?? '', 'claude') === false)
        );
        
        wp_send_json_success($debug_info);
    }
    
    public function handle_test_image_format() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $claude_api = new WP_Claude_Code_Claude_API();
        $settings = get_option('wp_claude_code_settings', array());
        
        // Create a simple test image (1x1 pixel base64 PNG)
        $test_image_base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        
        // Create a temporary test image URL by saving the test image
        $test_image_url = $this->create_test_image_url($test_image_base64);
        
        $test_results = array();
        
        // Test OpenAI format with URL (preferred method)
        $openai_url_message = array(
            'role' => 'user',
            'content' => array(
                array('type' => 'text', 'text' => 'Test image URL'),
                array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => $test_image_url
                    )
                )
            )
        );
        
        $test_results['openai_url_format'] = $this->test_api_request($openai_url_message, 'gpt-4o-mini');
        
        // Test OpenAI format with base64 (fallback method)
        $openai_base64_message = array(
            'role' => 'user',
            'content' => array(
                array('type' => 'text', 'text' => 'Test image base64'),
                array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => 'data:image/png;base64,' . $test_image_base64
                    )
                )
            )
        );
        
        $test_results['openai_base64_format'] = $this->test_api_request($openai_base64_message, 'gpt-4o-mini');
        
        // Test Claude format with base64 (Claude doesn't officially support URLs)
        $claude_message = array(
            'role' => 'user',
            'content' => array(
                array('type' => 'text', 'text' => 'Test image'),
                array(
                    'type' => 'image',
                    'source' => array(
                        'type' => 'base64',
                        'media_type' => 'image/png',
                        'data' => $test_image_base64
                    )
                )
            )
        );
        
        $test_results['claude_format'] = $this->test_api_request($claude_message, 'claude-3-sonnet');
        
        // Clean up test image
        $this->cleanup_test_image($test_image_url);
        
        wp_send_json_success($test_results);
    }
    
    private function create_test_image_url($base64_data) {
        // Create a temporary test image in uploads directory
        $upload_dir = wp_upload_dir();
        $test_dir = trailingslashit($upload_dir['basedir']) . 'claude-code-temp';
        
        if (!file_exists($test_dir)) {
            wp_mkdir_p($test_dir);
        }
        
        $test_filename = 'test_image_' . uniqid() . '.png';
        $test_filepath = trailingslashit($test_dir) . $test_filename;
        
        // Decode and save the test image
        $image_data = base64_decode($base64_data);
        file_put_contents($test_filepath, $image_data);
        
        // Return the URL
        $test_url = trailingslashit($upload_dir['baseurl']) . 'claude-code-temp/' . $test_filename;
        return $test_url;
    }
    
    private function cleanup_test_image($test_url) {
        // Extract filename from URL and delete the test image
        $upload_dir = wp_upload_dir();
        $relative_url = str_replace($upload_dir['baseurl'], '', $test_url);
        $filepath = $upload_dir['basedir'] . $relative_url;
        
        if (file_exists($filepath) && strpos($filepath, 'claude-code-temp/test_image_') !== false) {
            unlink($filepath);
        }
    }
    
    private function test_api_request($message, $model) {
        $settings = get_option('wp_claude_code_settings', array());
        $endpoint = $settings['litellm_endpoint'] ?? '';
        
        if (empty($endpoint)) {
            return array('success' => false, 'error' => 'No endpoint configured');
        }
        
        $url = rtrim($endpoint, '/') . '/v1/chat/completions';
        
        $data = array(
            'model' => $model,
            'messages' => array($message),
            'max_tokens' => 10
        );
        
        $headers = array('Content-Type' => 'application/json');
        if (!empty($settings['api_key'])) {
            $headers['Authorization'] = 'Bearer ' . $settings['api_key'];
        }
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'success' => $status_code === 200,
            'status_code' => $status_code,
            'response' => $status_code === 200 ? 'Success' : $body
        );
    }
    
    public function handle_test_litellm_direct() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $settings = get_option('wp_claude_code_settings', array());
        $endpoint = $settings['litellm_endpoint'] ?? '';
        $api_key = $settings['api_key'] ?? '';
        
        if (empty($endpoint)) {
            wp_send_json_error('No LiteLLM endpoint configured');
        }
        
        $url = rtrim($endpoint, '/') . '/v1/chat/completions';
        
        // Test with a simple text message first
        $text_message = array(
            'model' => 'gpt-4o-mini',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Respond with just "OK" to confirm the connection is working.'
                )
            ),
            'max_tokens' => 10
        );
        
        $headers = array('Content-Type' => 'application/json');
        if (!empty($api_key)) {
            $headers['Authorization'] = 'Bearer ' . $api_key;
        }
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($text_message),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            wp_send_json_error('LiteLLM returned HTTP ' . $status_code . ': ' . $body);
        }
        
        $result = json_decode($body, true);
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            wp_send_json_error('Invalid response from LiteLLM: ' . $body);
        }
        
        wp_send_json_success(array(
            'message' => 'LiteLLM connection successful',
            'response' => $result['choices'][0]['message']['content'],
            'endpoint' => $endpoint,
            'model_used' => $result['model'] ?? 'unknown'
        ));
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
        $provider = $settings['api_provider'] ?? 'litellm';
        
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
                    'response' => $result['response'] ?? 'No response content',
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
    
}