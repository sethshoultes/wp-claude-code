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