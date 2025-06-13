<?php
/**
 * Direct test script for image format compatibility
 * Run this from command line: php test-image-formats.php
 */

// Your LiteLLM endpoint and API key
$endpoint = 'https://64.23.251.16.nip.io';
$api_key = 'sk-1234';

// Simple 1x1 pixel test image (PNG)
$test_image_base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

// Create a temporary test image URL
$test_image_data = base64_decode($test_image_base64);
$temp_file = '/tmp/test_image_' . uniqid() . '.png';
file_put_contents($temp_file, $test_image_data);
$test_image_url = 'https://via.placeholder.com/1x1.png'; // Public test image

echo "Testing LiteLLM Image Format Compatibility\n";
echo "=========================================\n";
echo "Endpoint: $endpoint\n";
echo "API Key: " . substr($api_key, 0, 10) . "...\n\n";

function test_format($name, $endpoint, $api_key, $message, $model) {
    echo "Testing $name...\n";
    
    $url = rtrim($endpoint, '/') . '/v1/chat/completions';
    
    $data = [
        'model' => $model,
        'messages' => [$message],
        'max_tokens' => 10
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "  ❌ CURL Error: $error\n\n";
        return false;
    }
    
    echo "  HTTP Status: $http_code\n";
    
    if ($http_code === 200) {
        echo "  ✅ SUCCESS\n";
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            echo "  Response: " . substr($result['choices'][0]['message']['content'], 0, 50) . "...\n";
        }
        echo "\n";
        return true;
    } else {
        echo "  ❌ FAILED\n";
        $result = json_decode($response, true);
        if (isset($result['error']['message'])) {
            echo "  Error: " . $result['error']['message'] . "\n";
        } else {
            echo "  Raw response: " . substr($response, 0, 200) . "...\n";
        }
        echo "\n";
        return false;
    }
}

// Test 1: OpenAI format with public URL
$openai_url_message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'What do you see in this image?'],
        [
            'type' => 'image_url',
            'image_url' => ['url' => $test_image_url]
        ]
    ]
];

test_format('OpenAI Format with Public URL', $endpoint, $api_key, $openai_url_message, 'gpt-4o-mini');

// Test 2: OpenAI format with base64
$openai_base64_message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'What do you see in this image?'],
        [
            'type' => 'image_url',
            'image_url' => ['url' => 'data:image/png;base64,' . $test_image_base64]
        ]
    ]
];

test_format('OpenAI Format with Base64', $endpoint, $api_key, $openai_base64_message, 'gpt-4o-mini');

// Test 3: Claude format with base64
$claude_message = [
    'role' => 'user',
    'content' => [
        ['type' => 'text', 'text' => 'What do you see in this image?'],
        [
            'type' => 'image',
            'source' => [
                'type' => 'base64',
                'media_type' => 'image/png',
                'data' => $test_image_base64
            ]
        ]
    ]
];

test_format('Claude Format with Base64', $endpoint, $api_key, $claude_message, 'claude-3-sonnet');

// Test 4: Simple text message to verify connection
$text_message = [
    'role' => 'user',
    'content' => 'Hello, can you respond with just "OK"?'
];

test_format('Simple Text Message (Connection Test)', $endpoint, $api_key, $text_message, 'gpt-4o-mini');

// Cleanup
if (file_exists($temp_file)) {
    unlink($temp_file);
}

echo "Test completed!\n";
?>