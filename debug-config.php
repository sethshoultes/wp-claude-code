<?php
/**
 * Debug configuration loader for WP Claude Code
 * Run this from WordPress admin to debug API key loading
 */

// Only run if in WordPress admin with proper permissions
if (!defined('ABSPATH') || !is_admin() || !current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>WP Claude Code Configuration Debug</h2>";

// Check our plugin settings
$our_settings = get_option('wp_claude_code_settings', array());
echo "<h3>Our Plugin Settings:</h3>";
echo "<pre>" . print_r($our_settings, true) . "</pre>";

// Check MemberPress AI settings
$mpai_settings = get_option('mpai_settings', array());
echo "<h3>MemberPress AI Settings:</h3>";
if (!empty($mpai_settings)) {
    // Redact sensitive keys for display
    $display_settings = $mpai_settings;
    foreach ($display_settings as $key => &$value) {
        if (strpos($key, 'key') !== false && !empty($value)) {
            $value = substr($value, 0, 10) . '...[REDACTED]';
        }
    }
    echo "<pre>" . print_r($display_settings, true) . "</pre>";
} else {
    echo "<p>No MemberPress AI settings found.</p>";
}

// Check if MemberPress AI classes are available
echo "<h3>MemberPress AI Class Availability:</h3>";
$classes_to_check = [
    '\MemberpressAiAssistant\Admin\MPAIKeyManager',
    '\MemberpressAiAssistant\Llm\Providers\AnthropicClient'
];

foreach ($classes_to_check as $class) {
    $exists = class_exists($class);
    echo "<p><strong>$class:</strong> " . ($exists ? "✅ Available" : "❌ Not found") . "</p>";
}

// Test API key retrieval
echo "<h3>API Key Retrieval Test:</h3>";
if (class_exists('\MemberpressAiAssistant\Admin\MPAIKeyManager')) {
    try {
        $key_manager = new \MemberpressAiAssistant\Admin\MPAIKeyManager();
        $api_key = $key_manager->get_api_key('anthropic');
        echo "<p><strong>MemberPress AI KeyManager Result:</strong> " . ($api_key ? "✅ Key retrieved (length: " . strlen($api_key) . ")" : "❌ No key found") . "</p>";
    } catch (Exception $e) {
        echo "<p><strong>MemberPress AI KeyManager Error:</strong> " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><strong>MemberPress AI KeyManager:</strong> ❌ Class not available</p>";
}

// Test our API client
echo "<h3>Our API Client Test:</h3>";
try {
    require_once WP_CLAUDE_CODE_PLUGIN_PATH . 'includes/class-claude-api.php';
    $claude_api = new WP_Claude_Code_Claude_API();
    
    // Use reflection to access private settings
    $reflection = new ReflectionClass($claude_api);
    $settings_property = $reflection->getProperty('settings');
    $settings_property->setAccessible(true);
    $settings = $settings_property->getValue($claude_api);
    
    echo "<p><strong>Endpoint:</strong> " . ($settings['litellm_endpoint'] ?? 'Not set') . "</p>";
    echo "<p><strong>API Key:</strong> " . (!empty($settings['api_key']) ? "✅ Available (length: " . strlen($settings['api_key']) . ")" : "❌ Not available") . "</p>";
    echo "<p><strong>Model:</strong> " . ($settings['model'] ?? 'Not set') . "</p>";
    echo "<p><strong>Image Format Override:</strong> " . ($settings['image_format_override'] ?? 'auto') . "</p>";
    
    // Show configuration advice
    $config_advice = $claude_api->get_configuration_advice();
    if (!empty($config_advice)) {
        echo "<h4>Configuration Recommendations:</h4>";
        foreach ($config_advice as $advice) {
            $color = $advice['type'] === 'warning' ? '#dc3232' : '#46b450';
            $bg_color = $advice['type'] === 'warning' ? '#f8d7da' : '#ecf7ed';
            echo "<div style='background: {$bg_color}; border-left: 3px solid {$color}; padding: 10px; margin: 10px 0;'>";
            echo "<strong>{$advice['title']}:</strong> {$advice['message']}";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error testing our API client:</strong> " . $e->getMessage() . "</p>";
}

// Test connection
echo "<h3>Connection Test:</h3>";
try {
    $result = $claude_api->test_connection();
    if (is_wp_error($result)) {
        echo "<p><strong>Connection test failed:</strong> " . $result->get_error_message() . "</p>";
    } else {
        echo "<p><strong>Connection test result:</strong></p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p><strong>Connection test error:</strong> " . $e->getMessage() . "</p>";
}

// Direct LiteLLM Connection Test
echo "<h3>Direct LiteLLM Connection Test:</h3>";
echo "<p>Test if your LiteLLM proxy is responding correctly:</p>";
echo "<button id='test-litellm-direct' class='button button-secondary'>Test LiteLLM Connection</button>";
echo "<div id='litellm-test-results' style='margin-top: 15px;'></div>";

// Image Format Testing
echo "<h3>Image Format Testing:</h3>";
echo "<p>Test both OpenAI and Claude image formats to determine which works with your LiteLLM proxy:</p>";
echo "<button id='test-image-formats' class='button button-primary'>Test Image Formats</button>";
echo "<div id='image-format-results' style='margin-top: 15px;'></div>";

echo "
<script>
jQuery(document).ready(function($) {
    // Direct LiteLLM test
    $('#test-litellm-direct').on('click', function() {
        var button = $(this);
        var results = $('#litellm-test-results');
        
        button.prop('disabled', true).text('Testing...');
        results.html('<p>Testing direct connection to LiteLLM...</p>');
        
        $.ajax({
            url: '" . admin_url('admin-ajax.php') . "',
            type: 'POST',
            data: {
                action: 'claude_code_test_litellm_direct',
                nonce: '" . wp_create_nonce('claude_code_nonce') . "'
            },
            success: function(response) {
                button.prop('disabled', false).text('Test LiteLLM Connection');
                
                if (response.success) {
                    var html = '<div style=\"background: #ecf7ed; border: 1px solid #46b450; border-radius: 4px; padding: 15px;\">';
                    html += '<h4>✅ LiteLLM Connection Successful</h4>';
                    html += '<p><strong>Endpoint:</strong> ' + response.data.endpoint + '</p>';
                    html += '<p><strong>Model Used:</strong> ' + response.data.model_used + '</p>';
                    html += '<p><strong>Response:</strong> ' + response.data.response + '</p>';
                    html += '</div>';
                    results.html(html);
                } else {
                    results.html('<div style=\"background: #f8d7da; border: 1px solid #dc3232; border-radius: 4px; padding: 15px;\"><strong>❌ Connection Failed:</strong> ' + response.data + '</div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Test LiteLLM Connection');
                results.html('<div style=\"color: #dc3232;\">Network error occurred during test</div>');
            }
        });
    });
    
    $('#test-image-formats').on('click', function() {
        var button = $(this);
        var results = $('#image-format-results');
        
        button.prop('disabled', true).text('Testing...');
        results.html('<p>Testing image formats with your LiteLLM proxy...</p>');
        
        $.ajax({
            url: '" . admin_url('admin-ajax.php') . "',
            type: 'POST',
            data: {
                action: 'claude_code_test_image_format',
                nonce: '" . wp_create_nonce('claude_code_nonce') . "'
            },
            success: function(response) {
                button.prop('disabled', false).text('Test Image Formats');
                
                if (response.success) {
                    var data = response.data;
                    var html = '<div style=\"background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px; padding: 15px;\">';
                    html += '<h4>Image Format Test Results</h4>';
                    
                    // OpenAI URL Format Test (preferred)
                    html += '<div style=\"margin: 10px 0; padding: 10px; border-left: 3px solid ';
                    if (data.openai_url_format.success) {
                        html += '#46b450; background: #ecf7ed;\">';
                        html += '<strong>✅ OpenAI URL Format:</strong> SUCCESS (Recommended)';
                        html += '<br><small>✨ This is the most efficient method - images are served as URLs</small>';
                    } else {
                        html += '#dc3232; background: #f8d7da;\">';
                        html += '<strong>❌ OpenAI URL Format:</strong> FAILED';
                        html += '<br><small>Error: ' + (data.openai_url_format.response || 'Unknown error') + '</small>';
                    }
                    html += '</div>';
                    
                    // OpenAI Base64 Format Test (fallback)
                    html += '<div style=\"margin: 10px 0; padding: 10px; border-left: 3px solid ';
                    if (data.openai_base64_format.success) {
                        html += '#46b450; background: #ecf7ed;\">';
                        html += '<strong>✅ OpenAI Base64 Format:</strong> SUCCESS (Fallback)';
                        html += '<br><small>📊 Uses embedded base64 data - larger API requests</small>';
                    } else {
                        html += '#dc3232; background: #f8d7da;\">';
                        html += '<strong>❌ OpenAI Base64 Format:</strong> FAILED';
                        html += '<br><small>Error: ' + (data.openai_base64_format.response || 'Unknown error') + '</small>';
                    }
                    html += '</div>';
                    
                    // Claude Format Test
                    html += '<div style=\"margin: 10px 0; padding: 10px; border-left: 3px solid ';
                    if (data.claude_format.success) {
                        html += '#46b450; background: #ecf7ed;\">';
                        html += '<strong>✅ Claude Format:</strong> SUCCESS';
                        html += '<br><small>🔧 Uses Claude-specific base64 format</small>';
                    } else {
                        html += '#dc3232; background: #f8d7da;\">';
                        html += '<strong>❌ Claude Format:</strong> FAILED';
                        html += '<br><small>Error: ' + (data.claude_format.response || 'Unknown error') + '</small>';
                    }
                    html += '</div>';
                    
                    // Recommendation
                    html += '<div style=\"margin: 15px 0; padding: 10px; background: #e1f5fe; border-radius: 4px;\">';
                    html += '<strong>💡 Recommendation:</strong><br>';
                    
                    if (data.openai_url_format.success) {
                        html += '🎯 <strong>Use OpenAI format</strong> in Claude Code settings. Your LiteLLM proxy supports URL-based images (most efficient method).';
                    } else if (data.openai_base64_format.success) {
                        html += '📊 <strong>Use OpenAI format</strong> in Claude Code settings. Your LiteLLM proxy supports base64 images.';
                    } else if (data.claude_format.success) {
                        html += '🔧 <strong>Use Claude format</strong> in Claude Code settings. Your LiteLLM proxy works with Claude-style image messages.';
                    } else {
                        html += '⚠️ <strong>No image format is working.</strong> Check your LiteLLM proxy configuration and ensure it supports vision models. Verify the endpoint and API key are correct.';
                    }
                    html += '</div>';
                    
                    html += '</div>';
                    results.html(html);
                } else {
                    results.html('<div style=\"color: #dc3232;\">Test failed: ' + (response.data || 'Unknown error') + '</div>');
                }
            },
            error: function() {
                button.prop('disabled', false).text('Test Image Formats');
                results.html('<div style=\"color: #dc3232;\">Network error occurred during test</div>');
            }
        });
    });
});
</script>
";