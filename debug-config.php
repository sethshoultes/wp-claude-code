<?php
/**
 * Debug configuration for WP Claude Code
 * Shows current plugin configuration and settings for troubleshooting
 */

// Only run if in WordPress admin with proper permissions
if (!defined('ABSPATH') || !is_admin() || !current_user_can('manage_options')) {
    die('Access denied');
}

// AJAX actions are handled by the admin class
// This file only contains rendering and utility functions

// Initialize tab system
$active_tab = $_GET['debug_tab'] ?? 'system';

echo '<div class="debug-tabs-wrapper">';
echo '<nav class="nav-tab-wrapper wp-clearfix debug-nav-tabs">';
echo '<a href="?page=claude-code-debug&debug_tab=system" class="nav-tab ' . ($active_tab === 'system' ? 'nav-tab-active' : '') . '">📊 System Status</a>';
echo '<a href="?page=claude-code-debug&debug_tab=logs" class="nav-tab ' . ($active_tab === 'logs' ? 'nav-tab-active' : '') . '">📝 Debug Logs</a>';
echo '</nav>';

echo '<div class="debug-tab-content">';

if ($active_tab === 'logs') {
    wp_claude_code_render_logs_tab();
} else {
    wp_claude_code_render_system_tab();
}

echo '</div></div>';

function wp_claude_code_render_logs_tab() {
    ?>
    <div class="debug-logs-tab">
        <h2>📝 Debug Logs</h2>
        <p>View recent debug log entries from the WP Claude Code plugin. Logs help troubleshoot API connections, tool usage, and other plugin activities.</p>
        
        <?php
        // Debug status section removed - functionality works regardless of WordPress debug settings
        ?>
        
        <div class="logs-controls">
            <div class="logs-filters">
                <input type="text" id="log-filter" placeholder="Filter logs (e.g., 'error', 'API', 'tool')" />
                <select id="log-per-page">
                    <option value="25">25 per page</option>
                    <option value="50" selected>50 per page</option>
                    <option value="100">100 per page</option>
                </select>
            </div>
            <div class="logs-actions">
                <button id="refresh-logs" class="button button-secondary">🔄 Refresh</button>
                <button id="clear-logs" class="button button-secondary">🗑️ Clear Logs</button>
                <button id="download-logs" class="button button-secondary">💾 Download</button>
            </div>
        </div>
        
        <div id="logs-status" class="logs-status"></div>
        
        <div class="logs-container">
            <div id="logs-loading" class="logs-loading">
                <p>Loading debug logs...</p>
            </div>
            
            <div id="logs-content" class="logs-content" style="display: none;">
                <div class="logs-info">
                    <p>Showing <span id="logs-count">0</span> entries from <span id="logs-sources">0</span> log files</p>
                </div>
                
                <div class="logs-table-container">
                    <table class="wp-list-table widefat fixed striped logs-table">
                        <thead>
                            <tr>
                                <th class="timestamp-column">Timestamp</th>
                                <th class="level-column">Level</th>
                                <th class="message-column">Message</th>
                                <th class="source-column">Source</th>
                            </tr>
                        </thead>
                        <tbody id="logs-table-body">
                        </tbody>
                    </table>
                </div>
                
                <div class="logs-pagination">
                    <button id="logs-prev-page" class="button" disabled>← Previous</button>
                    <span id="logs-page-info">Page 1 of 1</span>
                    <button id="logs-next-page" class="button" disabled>Next →</button>
                </div>
            </div>
            
        </div>
    </div>
    
    <style>
    .debug-tabs-wrapper {
        background: #fff;
        border: 1px solid #c3c4c7;
        border-radius: 4px;
        margin: 20px 0;
    }
    
    /* Force hide any "No debug logs found" messages when logs exist */
    .logs-empty {
        display: none !important;
    }
    
    .notice:has-text("No debug logs found") {
        display: none !important;
    }
    
    .debug-nav-tabs {
        border-bottom: 1px solid #c3c4c7;
        background: #f6f7f7;
        margin: 0;
        padding: 0;
    }
    
    .debug-tab-content {
        padding: 20px;
    }
    
    .logs-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 20px 0;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .logs-filters {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .logs-actions {
        display: flex;
        gap: 10px;
    }
    
    #log-filter {
        width: 300px;
    }
    
    .logs-status {
        margin: 10px 0;
    }
    
    .logs-container {
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #fff;
    }
    
    .logs-loading, .logs-empty {
        padding: 40px;
        text-align: center;
        color: #666;
    }
    
    .logs-info {
        padding: 10px 15px;
        background: #f9f9f9;
        border-bottom: 1px solid #ddd;
        font-size: 13px;
        color: #666;
    }
    
    .logs-table-container {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .logs-table {
        margin: 0;
    }
    
    .logs-table th {
        position: sticky;
        top: 0;
        background: #f1f1f1;
        z-index: 10;
    }
    
    .timestamp-column { width: 150px; }
    .level-column { width: 80px; }
    .message-column { width: auto; }
    .source-column { width: 100px; }
    
    .log-level {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .log-level-error {
        background: #dc3232;
        color: white;
    }
    
    .log-level-warning {
        background: #ffb900;
        color: #000;
    }
    
    .log-level-notice {
        background: #00a0d2;
        color: white;
    }
    
    .log-level-info {
        background: #72aee6;
        color: white;
    }
    
    .log-message {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        word-break: break-word;
        max-width: 600px;
    }
    
    .logs-pagination {
        padding: 15px;
        text-align: center;
        border-top: 1px solid #ddd;
        background: #f9f9f9;
    }
    
    .logs-pagination button {
        margin: 0 5px;
    }
    
    #logs-page-info {
        margin: 0 15px;
        font-weight: bold;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let currentPage = 1;
        let totalPages = 1;
        let isLoading = false;
        
        console.log('Debug logs page loaded - no empty state needed');
        
        // Load initial logs
        loadLogs();
        
        // Filter input
        $('#log-filter').on('input', debounce(function() {
            currentPage = 1;
            loadLogs();
        }, 500));
        
        // Per page selector
        $('#log-per-page').on('change', function() {
            currentPage = 1;
            loadLogs();
        });
        
        // Control buttons
        $('#refresh-logs').on('click', function() {
            loadLogs();
        });
        
        $('#clear-logs').on('click', function() {
            if (confirm('Are you sure you want to clear all debug logs? This action cannot be undone.')) {
                clearLogs();
            }
        });
        
        $('#download-logs').on('click', function() {
            downloadLogs();
        });
        
        // Pagination
        $('#logs-prev-page').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadLogs();
            }
        });
        
        $('#logs-next-page').on('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadLogs();
            }
        });
        
        function loadLogs() {
            if (isLoading) return;
            
            isLoading = true;
            showLoading();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_claude_code_debug_logs',
                    debug_action: 'get_logs',
                    nonce: '<?php echo wp_create_nonce('claude_code_nonce'); ?>',
                    page: currentPage,
                    per_page: $('#log-per-page').val(),
                    filter: $('#log-filter').val()
                },
                success: function(response) {
                    if (response.success) {
                        displayLogs(response.data);
                    } else {
                        showError('Failed to load logs: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    showError('Network error: ' + error);
                },
                complete: function() {
                    isLoading = false;
                }
            });
        }
        
        function clearLogs() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_claude_code_debug_logs',
                    debug_action: 'clear_logs',
                    nonce: '<?php echo wp_create_nonce('claude_code_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.data.message);
                        loadLogs(); // Reload logs
                    } else {
                        showError('Failed to clear logs: ' + (response.data?.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    showError('Network error: ' + error);
                }
            });
        }
        
        function downloadLogs() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wp_claude_code_debug_logs',
                    debug_action: 'download_logs',
                    nonce: '<?php echo wp_create_nonce('claude_code_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.content) {
                        downloadFile('wp-claude-code-debug-logs.txt', response.data.content);
                        
                        // Show message if debug logging is disabled
                        if (response.data.content.includes('Debug logging is currently disabled')) {
                            showSuccess('Debug information downloaded. Note: Debug logging is currently disabled in WordPress.');
                        }
                    } else {
                        showError('Failed to prepare log content for download');
                    }
                },
                error: function(xhr, status, error) {
                    showError('Network error: ' + error);
                }
            });
        }
        
        function displayLogs(data) {
            const tbody = $('#logs-table-body');
            tbody.empty();
            
            // Debug logging to help troubleshoot visibility issues
            console.log('Debug logs data:', data);
            console.log('Total entries:', data.total);
            
            // Check if there are any logs in the system (total > 0), not just current page entries
            if (data.total && data.total > 0) {
                // Ensure empty state is hidden and content is shown
                $('#logs-empty').hide();
                $('#logs-content').show();
                console.log('Showing logs content, hiding empty state');
                
                // Update info
                $('#logs-count').text(data.total);
                $('#logs-sources').text(data.log_files ? data.log_files.length : 0);
                
                // Populate table if there are entries on current page
                if (data.entries && data.entries.length > 0) {
                    data.entries.forEach(function(entry) {
                        const row = $('<tr>');
                        row.append($('<td>').text(entry.timestamp));
                        row.append($('<td>').html('<span class="log-level log-level-' + entry.level + '">' + entry.level + '</span>'));
                        row.append($('<td>').html('<div class="log-message">' + escapeHtml(entry.message) + '</div>'));
                        row.append($('<td>').text(entry.source));
                        tbody.append(row);
                    });
                } else {
                    // Show message when no entries on current page due to filtering
                    tbody.append('<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">No entries match the current filter on this page.</td></tr>');
                }
                
                // Update pagination
                totalPages = data.total_pages || 1;
                updatePagination();
            } else {
                // Ensure content is hidden and empty state is shown
                $('#logs-content').hide();
                $('#logs-empty').show();
                console.log('No logs found, showing empty state');
            }
            
            hideLoading();
        }
        
        function updatePagination() {
            $('#logs-page-info').text('Page ' + currentPage + ' of ' + totalPages);
            $('#logs-prev-page').prop('disabled', currentPage <= 1);
            $('#logs-next-page').prop('disabled', currentPage >= totalPages);
        }
        
        function showLoading() {
            $('#logs-loading').show();
            $('#logs-content, #logs-empty').hide();
        }
        
        function hideLoading() {
            $('#logs-loading').hide();
        }
        
        function showError(message) {
            $('#logs-status').html('<div class="notice notice-error"><p>' + escapeHtml(message) + '</p></div>');
            hideLoading();
        }
        
        function showSuccess(message) {
            $('#logs-status').html('<div class="notice notice-success"><p>' + escapeHtml(message) + '</p></div>');
            setTimeout(function() {
                $('#logs-status').empty();
            }, 3000);
        }
        
        function downloadFile(filename, content) {
            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }
        
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    });
    </script>
    <?php
}

function wp_claude_code_render_system_tab() {

?>
    <div class="system-status-tab">
        <h2>📊 System Status</h2>
        <p>Current plugin configuration and environment information for troubleshooting.</p>
        
        <?php
        // Get plugin settings
        $settings = get_option('wp_claude_code_settings', array());
        
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
            'staging' => 'Staging Management'
        );
        
        echo "<div style='background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px;'>";
        foreach ($available_tools as $tool => $label) {
            $enabled = in_array($tool, $enabled_tools);
            $status = $enabled ? "✅ Enabled" : "❌ Disabled";
            echo "<p><strong>$label:</strong> $status</p>";
        }
        echo "</div>";
        ?>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                var button = $(this);
                var results = $('#connection-test-results');
                
                button.prop('disabled', true).text('Testing...');
                results.html('<p>Testing API connection...</p>');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'claude_code_test_connection',
                        nonce: '<?php echo wp_create_nonce('claude_code_nonce'); ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        button.prop('disabled', false).text('Test API Connection');
                        
                        if (response.success) {
                            var html = '<div style="background: #ecf7ed; border: 1px solid #46b450; border-radius: 4px; padding: 15px;">';
                            html += '<h4>✅ Connection Successful</h4>';
                            html += '<p><strong>Provider:</strong> ' + (response.data.provider || 'Unknown') + '</p>';
                            html += '<p><strong>Response:</strong> ' + (response.data.response || 'No response content') + '</p>';
                            html += '</div>';
                            results.html(html);
                        } else {
                            var html = '<div style="background: #f8d7da; border: 1px solid #dc3232; border-radius: 4px; padding: 15px;">';
                            html += '<h4>❌ Connection Failed</h4>';
                            html += '<p><strong>Error:</strong> ' + (response.data.message || response.data || 'Unknown error') + '</p>';
                            html += '<p><strong>Provider:</strong> ' + (response.data.provider || 'Unknown') + '</p>';
                            html += '</div>';
                            results.html(html);
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('Test API Connection');
                        var html = '<div style="background: #f8d7da; border: 1px solid #dc3232; border-radius: 4px; padding: 15px;">';
                        html += '<h4>❌ Network Error</h4>';
                        html += '<p>Failed to connect to the server. Status: ' + status + '</p>';
                        if (error) html += '<p>Error: ' + error + '</p>';
                        html += '</div>';
                        results.html(html);
                    }
                });
            });
        });
        </script>
    </div>
    <?php
}