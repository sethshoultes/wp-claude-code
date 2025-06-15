<?php
/**
 * Test script to verify the tool detection fix
 * Access this via: /wp-content/plugins/wp-claude-code/test-tool-fix.php
 */

// Include WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Only run this in development/testing
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    wp_die('This is a development test file.');
}

echo "<h1>WP Claude Code Tool Detection Test</h1>\n";

// Include the classes we need
require_once dirname(__FILE__) . '/includes/class-content-manager.php';

$content_manager = WP_Claude_Code_Content_Manager::get_instance();

echo "<h2>Test 1: Without tool execution flag (should return JSON)</h2>\n";
$result1 = $content_manager->get_posts(array('limit' => 3));
echo "<pre>" . print_r($result1, true) . "</pre>\n";

echo "<h2>Test 2: With tool execution flag (should return formatted string)</h2>\n";
// Simulate tool execution context
if (!defined('WP_CLAUDE_CODE_TOOL_EXECUTION')) {
    define('WP_CLAUDE_CODE_TOOL_EXECUTION', true);
}

$result2 = $content_manager->get_posts(array('limit' => 3));
echo "<pre>" . htmlspecialchars($result2) . "</pre>\n";

echo "<h2>Test 3: Test get_post method</h2>\n";
// Get the first post to test single post display
$posts = get_posts(array('numberposts' => 1));
if (!empty($posts)) {
    $result3 = $content_manager->get_post(array('post_id' => $posts[0]->ID));
    echo "<pre>" . htmlspecialchars($result3) . "</pre>\n";
} else {
    echo "<p>No posts available to test get_post method.</p>\n";
}

echo "<p><strong>Test completed.</strong> If Test 2 shows formatted markdown-style text instead of JSON, the fix is working!</p>\n";