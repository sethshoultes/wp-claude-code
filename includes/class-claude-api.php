<?php

class WP_Claude_Code_Claude_API {
    
    private $settings;
    private $current_model;
    private $api_provider;
    
    public function __construct() {
        $this->settings = get_option('wp_claude_code_settings', array());
        $this->current_model = $this->settings['model'] ?? 'claude-3-sonnet';
        
        // Use explicit provider setting, only fall back to auto-detection if not set
        if (!empty($this->settings['api_provider']) && $this->settings['api_provider'] !== 'auto') {
            $this->api_provider = $this->settings['api_provider'];
        } else {
            $this->api_provider = $this->detect_provider_from_model($this->current_model);
        }
        
        // Settings loaded - ready to use
    }
    
    /**
     * Detect appropriate provider based on model name
     */
    private function detect_provider_from_model($model) {
        if (strpos($model, 'claude') !== false) {
            return 'claude_direct';
        } elseif (strpos($model, 'gpt') !== false) {
            return 'openai_direct';
        }
        return 'litellm_proxy'; // Default to LiteLLM proxy for unknown models
    }
    
    /**
     * Set the model to use for this request
     */
    public function set_model($model) {
        $this->current_model = sanitize_text_field($model);
        
        // Always auto-update provider based on model to enable cross-provider switching
        $this->api_provider = $this->detect_provider_from_model($model);
    }
    
    /**
     * Get the current model
     */
    public function get_model() {
        return $this->current_model;
    }
    
    /**
     * Set the API provider to use
     */
    public function set_api_provider($provider) {
        $this->api_provider = sanitize_text_field($provider);
    }
    
    /**
     * Get the current API provider
     */
    public function get_api_provider() {
        return $this->api_provider;
    }
    
    
    public function send_message($message, $conversation_id = '', $attachments = array()) {
        // Route to appropriate API provider
        switch ($this->api_provider) {
            case 'litellm_proxy':
                return $this->send_message_litellm_proxy($message, $conversation_id, $attachments);
            case 'claude_direct':
                return $this->send_message_claude_direct($message, $conversation_id, $attachments);
            case 'openai_direct':
            default:
                return $this->send_message_openai_direct($message, $conversation_id, $attachments);
        }
    }
    
    
    /**
     * Send message via direct Claude API
     */
    private function send_message_claude_direct($message, $conversation_id = '', $attachments = array()) {
        $api_key = $this->settings['claude_api_key'] ?? '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Claude API key not configured');
        }
        
        $conversation_history = $this->get_conversation_history($conversation_id);
        $system_prompt = $this->get_system_prompt();
        $tools = $this->get_available_tools_claude();
        
        $messages = array();
        
        // Add conversation history (Claude doesn't use system role in messages)
        foreach ($conversation_history as $msg) {
            $messages[] = array(
                'role' => $msg->message_type === 'user' ? 'user' : 'assistant',
                'content' => $msg->content
            );
        }
        
        // Prepare current message with Claude-specific attachment format
        $current_message = $this->prepare_message_claude_format($message, $attachments);
        $messages[] = $current_message;
        
        $request_data = array(
            'model' => $this->map_model_to_claude($this->current_model),
            'max_tokens' => intval($this->settings['max_tokens'] ?? 4000),
            'system' => $system_prompt,
            'messages' => $messages,
            'tools' => $tools
        );
        
        error_log('WP Claude Code: Direct Claude API Request - Model: ' . $this->current_model);
        error_log('WP Claude Code: Direct Claude API Request - Has attachments: ' . (!empty($attachments) ? 'Yes' : 'No'));
        
        $response = $this->make_claude_api_request($request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->process_claude_response($response);
    }
    
    /**
     * Send message via direct OpenAI API
     */
    private function send_message_openai_direct($message, $conversation_id = '', $attachments = array()) {
        $api_key = $this->settings['openai_api_key'] ?? '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }
        
        $conversation_history = $this->get_conversation_history($conversation_id);
        $system_prompt = $this->get_system_prompt();
        $tools = $this->get_available_tools_openai();
        
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
        
        // Prepare current message with OpenAI-specific attachment format
        $current_message = $this->prepare_message_openai_format($message, $attachments);
        $messages[] = $current_message;
        
        $request_data = array(
            'model' => $this->map_model_to_openai($this->current_model),
            'messages' => $messages,
            'max_tokens' => intval($this->settings['max_tokens'] ?? 4000),
            'tools' => $tools
        );
        
        error_log('WP Claude Code: Direct OpenAI API Request - Model: ' . $this->current_model);
        error_log('WP Claude Code: Direct OpenAI API Request - Has attachments: ' . (!empty($attachments) ? 'Yes' : 'No'));
        
        $response = $this->make_openai_api_request($request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->process_openai_response($response);
    }
    
    /**
     * Send message via LiteLLM proxy
     */
    private function send_message_litellm_proxy($message, $conversation_id = '', $attachments = array()) {
        
        $conversation_history = $this->get_conversation_history($conversation_id);
        $system_prompt = $this->get_system_prompt();
        $tools = $this->get_available_tools_litellm();
        
        $messages = array();
        
        // Add system message (LiteLLM proxy supports OpenAI format)
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
        
        // Prepare current message with attachments
        $current_message = $this->prepare_message_litellm_format($message, $attachments);
        $messages[] = $current_message;
        
        $request_data = array(
            'model' => $this->current_model,
            'messages' => $messages,
            'max_tokens' => intval($this->settings['max_tokens'] ?? 4000),
            'tools' => $tools
        );
        
        error_log('WP Claude Code: LiteLLM Proxy Request - Model: ' . $this->current_model);
        error_log('WP Claude Code: LiteLLM Proxy Request - Has attachments: ' . (!empty($attachments) ? 'Yes' : 'No'));
        
        $response = $this->make_litellm_proxy_request($request_data, '3d82afe47512fcb1faba41cc1c9c796d3dbe8624b0a5c62fa68e6d38f0bf6d72');
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $this->process_litellm_proxy_response($response);
    }
    
    /**
     * Prepare message with attachments for LiteLLM proxy format
     */
    private function prepare_message_litellm_format($message, $attachment_ids) {
        if (empty($attachment_ids)) {
            return array(
                'role' => 'user',
                'content' => $message
            );
        }
        
        $content_parts = array();
        
        // Add text message
        $content_parts[] = array(
            'type' => 'text',
            'text' => $message
        );
        
        // Process attachments for LiteLLM proxy (uses OpenAI format)
        foreach ($attachment_ids as $attachment_id) {
            $attachment_data = WP_Claude_Code_File_Attachment::execute_attachment_tool('read_attachment', array('attachment_id' => $attachment_id));
            
            if (is_wp_error($attachment_data) || !$attachment_data['success']) {
                continue;
            }
            
            if ($attachment_data['type'] === 'text') {
                $content_parts[] = array(
                    'type' => 'text',
                    'text' => "\n\n--- File: {$attachment_data['filename']} ---\n" . $attachment_data['content'] . "\n--- End of file ---\n"
                );
            } elseif ($attachment_data['type'] === 'image') {
                // LiteLLM proxy uses OpenAI format for images
                $content_parts[] = array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => "data:{$attachment_data['media_type']};base64,{$attachment_data['base64_data']}"
                    )
                );
            }
        }
        
        return array(
            'role' => 'user',
            'content' => $content_parts
        );
    }
    
    /**
     * Make LiteLLM proxy API request
     */
    private function make_litellm_proxy_request($data, $api_key) {
        $url = 'https://64.23.251.16.nip.io/chat/completions';
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        );
        
        return $this->make_http_request($url, $data, $headers);
    }
    
    /**
     * Process LiteLLM proxy response (uses OpenAI format)
     */
    private function process_litellm_proxy_response($response) {
        return $this->process_openai_response($response);
    }
    
    /**
     * Get LiteLLM proxy tools format (uses OpenAI format)
     */
    private function get_available_tools_litellm() {
        return $this->get_available_tools_openai();
    }
    
    /**
     * Prepare message with attachments for AI processing
     */
    private function prepare_message_with_attachments($message, $attachment_ids) {
        if (empty($attachment_ids)) {
            return array(
                'role' => 'user',
                'content' => $message
            );
        }
        
        // Check if model supports vision (images)
        $supports_vision = $this->model_supports_vision($this->current_model);
        
        $content_parts = array();
        
        // Add text message
        $content_parts[] = array(
            'type' => 'text',
            'text' => $message
        );
        
        // Process attachments
        foreach ($attachment_ids as $attachment_id) {
            $attachment_data = WP_Claude_Code_File_Attachment::execute_attachment_tool('read_attachment', array('attachment_id' => $attachment_id));
            
            if (is_wp_error($attachment_data) || !$attachment_data['success']) {
                error_log('WP Claude Code: Failed to read attachment: ' . $attachment_id);
                continue;
            }
            
            error_log('WP Claude Code: Processing attachment: ' . $attachment_data['filename'] . ' (type: ' . $attachment_data['type'] . ')');
            
            if ($attachment_data['type'] === 'text') {
                // Add text file content
                $content_parts[] = array(
                    'type' => 'text',
                    'text' => "\n\n--- File: {$attachment_data['filename']} ---\n" . $attachment_data['content'] . "\n--- End of file ---\n"
                );
            } elseif ($attachment_data['type'] === 'image' && $supports_vision) {
                // Add image for vision-capable models with smart format detection
                $image_content = $this->format_image_for_model($attachment_data);
                if ($image_content) {
                    $content_parts[] = $image_content;
                } else {
                    // Fallback to text description if image processing fails
                    $content_parts[] = array(
                        'type' => 'text',
                        'text' => "\n\n--- Image File: {$attachment_data['filename']} ---\nNote: Could not process image for viewing with the current model configuration. Image processing failed.\n--- End of image reference ---\n"
                    );
                }
            } elseif ($attachment_data['type'] === 'image' && !$supports_vision) {
                // For non-vision models, describe the image
                $content_parts[] = array(
                    'type' => 'text',
                    'text' => "\n\n--- Image File: {$attachment_data['filename']} ---\nNote: This is an image file ({$attachment_data['mime_type']}) that I cannot directly view with the current model ({$this->current_model}). The image is {$this->format_file_size($attachment_data['size'])} in size. To analyze this image, please switch to a vision-capable model like Claude 3 Sonnet, Claude 3 Opus, Claude 3 Haiku, GPT-4o, or GPT-4o Mini.\n--- End of image reference ---\n"
                );
            }
        }
        
        // Return appropriate message format
        if (count($content_parts) === 1) {
            // Single text content
            return array(
                'role' => 'user',
                'content' => $content_parts[0]['text']
            );
        } else {
            // Multi-part content (text + images)
            return array(
                'role' => 'user',
                'content' => $content_parts
            );
        }
    }
    
    /**
     * Format image for direct API use (simplified from LiteLLM version)
     */
    private function format_image_for_model($attachment_data) {
        // Determine format based on current API provider
        if ($this->api_provider === 'claude_direct') {
            // Claude format - always use base64
            return array(
                'type' => 'image',
                'source' => array(
                    'type' => 'base64',
                    'media_type' => $attachment_data['media_type'],
                    'data' => $attachment_data['base64_data']
                )
            );
        } else {
            // OpenAI format - try URL first, fall back to base64
            if (!empty($attachment_data['url'])) {
                // Test if the URL is accessible
                $url_test = wp_remote_head($attachment_data['url'], array('timeout' => 5));
                if (!is_wp_error($url_test) && wp_remote_retrieve_response_code($url_test) === 200) {
                    return array(
                        'type' => 'image_url',
                        'image_url' => array(
                            'url' => $attachment_data['url']
                        )
                    );
                }
            }
            
            // Fallback to base64 for OpenAI
            return array(
                'type' => 'image_url',
                'image_url' => array(
                    'url' => "data:{$attachment_data['media_type']};base64,{$attachment_data['base64_data']}"
                )
            );
        }
    }
    
    /**
     * Prepare message with Claude-specific attachment format
     */
    private function prepare_message_claude_format($message, $attachment_ids) {
        if (empty($attachment_ids)) {
            return array(
                'role' => 'user',
                'content' => $message
            );
        }
        
        $content_parts = array();
        
        // Add text message
        $content_parts[] = array(
            'type' => 'text',
            'text' => $message
        );
        
        // Process attachments for Claude format
        foreach ($attachment_ids as $attachment_id) {
            $attachment_data = WP_Claude_Code_File_Attachment::execute_attachment_tool('read_attachment', array('attachment_id' => $attachment_id));
            
            if (is_wp_error($attachment_data) || !$attachment_data['success']) {
                continue;
            }
            
            if ($attachment_data['type'] === 'text') {
                $content_parts[] = array(
                    'type' => 'text',
                    'text' => "\n\n--- File: {$attachment_data['filename']} ---\n" . $attachment_data['content'] . "\n--- End of file ---\n"
                );
            } elseif ($attachment_data['type'] === 'image') {
                // Claude format for images
                $content_parts[] = array(
                    'type' => 'image',
                    'source' => array(
                        'type' => 'base64',
                        'media_type' => $attachment_data['media_type'],
                        'data' => $attachment_data['base64_data']
                    )
                );
            }
        }
        
        return array(
            'role' => 'user',
            'content' => $content_parts
        );
    }
    
    /**
     * Prepare message with OpenAI-specific attachment format
     */
    private function prepare_message_openai_format($message, $attachment_ids) {
        if (empty($attachment_ids)) {
            return array(
                'role' => 'user',
                'content' => $message
            );
        }
        
        $content_parts = array();
        
        // Add text message
        $content_parts[] = array(
            'type' => 'text',
            'text' => $message
        );
        
        // Process attachments for OpenAI format
        foreach ($attachment_ids as $attachment_id) {
            $attachment_data = WP_Claude_Code_File_Attachment::execute_attachment_tool('read_attachment', array('attachment_id' => $attachment_id));
            
            if (is_wp_error($attachment_data) || !$attachment_data['success']) {
                continue;
            }
            
            if ($attachment_data['type'] === 'text') {
                $content_parts[] = array(
                    'type' => 'text',
                    'text' => "\n\n--- File: {$attachment_data['filename']} ---\n" . $attachment_data['content'] . "\n--- End of file ---\n"
                );
            } elseif ($attachment_data['type'] === 'image') {
                // OpenAI format for images
                $content_parts[] = array(
                    'type' => 'image_url',
                    'image_url' => array(
                        'url' => "data:{$attachment_data['media_type']};base64,{$attachment_data['base64_data']}"
                    )
                );
            }
        }
        
        return array(
            'role' => 'user',
            'content' => $content_parts
        );
    }
    
    /**
     * Map generic model names to Claude API models
     */
    private function map_model_to_claude($model) {
        $claude_models = array(
            'claude-3-sonnet' => 'claude-3-sonnet-20240229',
            'claude-3-opus' => 'claude-3-opus-20240229',
            'claude-3-haiku' => 'claude-3-haiku-20240307'
        );
        
        return $claude_models[$model] ?? $model;
    }
    
    /**
     * Map generic model names to OpenAI API models
     */
    private function map_model_to_openai($model) {
        $openai_models = array(
            'gpt-4o' => 'gpt-4o',
            'gpt-4o-mini' => 'gpt-4o-mini',
            'gpt-4' => 'gpt-4',
            'gpt-3.5-turbo' => 'gpt-3.5-turbo'
        );
        
        return $openai_models[$model] ?? $model;
    }
    
    /**
     * Make direct Claude API request
     */
    private function make_claude_api_request($data) {
        $url = 'https://api.anthropic.com/v1/messages';
        $api_key = $this->settings['claude_api_key'];
        
        $headers = array(
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01'
        );
        
        return $this->make_http_request($url, $data, $headers);
    }
    
    /**
     * Make direct OpenAI API request
     */
    private function make_openai_api_request($data) {
        $url = 'https://api.openai.com/v1/chat/completions';
        $api_key = $this->settings['openai_api_key'];
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        );
        
        return $this->make_http_request($url, $data, $headers);
    }
    
    /**
     * Process Claude API response
     */
    private function process_claude_response($response) {
        if (isset($response['content']) && is_array($response['content']) && !empty($response['content'])) {
            $content = '';
            $tools_used = array();
            
            foreach ($response['content'] as $block) {
                if ($block['type'] === 'text') {
                    $content .= $block['text'];
                } elseif ($block['type'] === 'tool_use') {
                    $tools_used[] = $block['name'];
                    $tool_result = $this->execute_tool($block['name'], $block['input']);
                    if (!is_wp_error($tool_result)) {
                        // Add tool result directly without extra "Tool Result:" prefix
                        // The JavaScript will handle the formatting and wrapping
                        if (is_string($tool_result)) {
                            $content .= "\n\n" . $tool_result;
                        } elseif (is_array($tool_result) && isset($tool_result['message'])) {
                            // For tool results with a message field, use that for display
                            $content .= "\n\n" . $tool_result['message'];
                        } else {
                            $content .= "\n\n" . json_encode($tool_result, JSON_PRETTY_PRINT);
                        }
                    }
                }
            }
            
            return array(
                'content' => $content,
                'tools_used' => $tools_used
            );
        }
        
        return new WP_Error('invalid_response', 'Invalid response from Claude API');
    }
    
    /**
     * Process OpenAI API response
     */
    private function process_openai_response($response) {
        if (isset($response['choices']) && !empty($response['choices'])) {
            $choice = $response['choices'][0];
            $content = $choice['message']['content'] ?? '';
            $tools_used = array();
            
            if (isset($choice['message']['tool_calls'])) {
                foreach ($choice['message']['tool_calls'] as $tool_call) {
                    $function_name = $tool_call['function']['name'];
                    $arguments = json_decode($tool_call['function']['arguments'], true);
                    $tools_used[] = $function_name;
                    
                    $tool_result = $this->execute_tool($function_name, $arguments);
                    if (!is_wp_error($tool_result)) {
                        // Add tool result directly without extra "Tool Result:" prefix
                        // The JavaScript will handle the formatting and wrapping
                        if (is_string($tool_result)) {
                            $content .= "\n\n" . $tool_result;
                        } elseif (is_array($tool_result) && isset($tool_result['message'])) {
                            // For tool results with a message field, use that for display
                            $content .= "\n\n" . $tool_result['message'];
                        } else {
                            $content .= "\n\n" . json_encode($tool_result, JSON_PRETTY_PRINT);
                        }
                    }
                }
            }
            
            return array(
                'content' => $content,
                'tools_used' => $tools_used
            );
        }
        
        return new WP_Error('invalid_response', 'Invalid response from OpenAI API');
    }
    
    /**
     * Generic HTTP request handler
     */
    private function make_http_request($url, $data, $headers) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return new WP_Error('curl_error', 'HTTP request failed: ' . $error);
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code !== 200) {
            $error_message = isset($decoded['error']['message']) ? $decoded['error']['message'] : 'HTTP ' . $http_code;
            return new WP_Error('api_error', $error_message);
        }
        
        return $decoded;
    }
    
    /**
     * Get Claude-specific tools format
     */
    private function get_available_tools_claude() {
        // Convert tools to Claude format
        $litellm_tools = $this->get_available_tools();
        $claude_tools = array();
        
        foreach ($litellm_tools as $tool) {
            $claude_tools[] = array(
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'input_schema' => $tool['function']['parameters']
            );
        }
        
        return $claude_tools;
    }
    
    /**
     * Get OpenAI-specific tools format (same as LiteLLM)
     */
    private function get_available_tools_openai() {
        return $this->get_available_tools();
    }
    
    /**
     * Get available models for the current provider
     */
    public function get_available_models() {
        return $this->get_provider_specific_models();
    }
    
    /**
     * Get all available models regardless of current provider
     */
    private function get_provider_specific_models() {
        // Always return all models so users can switch between providers in chat
        return array(
            'claude' => array(
                array('id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'supports_vision' => true),
                array('id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'supports_vision' => true),
                array('id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'supports_vision' => true)
            ),
            'openai' => array(
                array('id' => 'gpt-4o', 'name' => 'GPT-4o', 'supports_vision' => true),
                array('id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'supports_vision' => true),
                array('id' => 'gpt-4-turbo', 'name' => 'GPT-4 Turbo', 'supports_vision' => true),
                array('id' => 'gpt-4', 'name' => 'GPT-4', 'supports_vision' => false)
            ),
            'other' => array()
        );
    }
    
    /**
     * Clear models cache (no longer needed for direct APIs but kept for compatibility)
     */
    public function clear_models_cache() {
        // No caching needed for direct APIs
        return true;
    }
    
    /**
     * Get troubleshooting suggestions for image processing errors
     */
    public function get_image_troubleshooting_suggestions() {
        $suggestions = array(
            'format_issues' => array(
                'title' => 'Image Format Compatibility',
                'suggestions' => array(
                    'Images are automatically formatted for your selected API provider',
                    'Claude API uses base64 encoding for all images',
                    'OpenAI API tries URLs first, falls back to base64 encoding'
                )
            ),
            'model_compatibility' => array(
                'title' => 'Model Configuration', 
                'suggestions' => array(
                    'Use vision-capable models: Claude 3.5 Sonnet, Claude 3 Opus, GPT-4o, GPT-4o-mini, GPT-4 Turbo',
                    'Ensure your API key has proper permissions',
                    'Check that the selected model supports image analysis'
                )
            ),
            'general_tips' => array(
                'title' => 'General Tips',
                'suggestions' => array(
                    'Check that your image is in supported format (JPEG, PNG, GIF, WebP)',
                    'Verify image size is under 10MB for best performance',
                    'Ensure stable internet connection for API requests',
                    'Try a different image if one specific image fails'
                )
            )
        );
        
        return $suggestions;
    }
    
    /**
     * Get specific configuration advice based on current setup
     */
    public function get_configuration_advice() {
        $advice = array();
        
        // Provide advice based on current API provider
        if ($this->api_provider === 'claude_direct') {
            $advice[] = array(
                'type' => 'info',
                'title' => 'Direct Claude API Configuration',
                'message' => 'You are using the direct Claude API. Images are automatically processed using base64 encoding for optimal compatibility.'
            );
        } elseif ($this->api_provider === 'openai_direct') {
            $advice[] = array(
                'type' => 'info',
                'title' => 'Direct OpenAI API Configuration',
                'message' => 'You are using the direct OpenAI API. Images are processed using URLs when possible, with base64 fallback for maximum efficiency.'
            );
        }
        
        return $advice;
    }
    
    
    /**
     * Check if model supports vision/image analysis
     */
    private function model_supports_vision($model) {
        $vision_models = array(
            // Claude models with vision
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
            'claude-3-sonnet',
            'claude-3-opus',
            'claude-3-haiku',
            // GPT models with vision
            'gpt-4-vision-preview',
            'gpt-4o',
            'gpt-4o-2024-05-13',
            'gpt-4o-mini',
            'gpt-4o-mini-2024-07-18',
            'gpt-4-turbo',
            'gpt-4-turbo-2024-04-09'
        );
        
        return in_array($model, $vision_models);
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        if ($bytes === 0) return '0 B';
        $k = 1024;
        $sizes = array('B', 'KB', 'MB', 'GB');
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 1) . ' ' . $sizes[$i];
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
- create_post: Create new posts, pages, and custom content with categories/tags
- update_post: Update existing posts and pages
- get_posts: List and search WordPress content
- get_post: Get detailed information about specific posts
- delete_post: Delete posts and pages (trash or permanent)
- read_attachment: Read and analyze uploaded files (text, code, images)
- list_attachments: List uploaded file attachments
- wp_plugin_check: Check WordPress.org plugin repository for plugin availability and details

VISION CAPABILITIES:
- I can analyze uploaded images when using vision-capable models (Claude 3 models, GPT-4o)
- Supported image formats: JPEG, PNG, GIF, WebP
- I can describe images, read text in images, analyze UI/UX designs, review diagrams
- For code screenshots, I can read and explain the code shown

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
        
        // Add content management tools
        $content_tools = WP_Claude_Code_Content_Manager::get_content_tools();
        foreach ($content_tools as $tool) {
            $tools[] = $tool;
        }
        
        // Add file attachment tools
        $attachment_tools = WP_Claude_Code_File_Attachment::get_attachment_tools();
        foreach ($attachment_tools as $tool) {
            $tools[] = $tool;
        }
        
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
    
    
    private function execute_tool($function_name, $arguments) {
        error_log("WP Claude Code: Executing tool: $function_name with arguments: " . json_encode($arguments));
        
        // Define constant to indicate we're in tool execution context
        if (!defined('WP_CLAUDE_CODE_TOOL_EXECUTION')) {
            define('WP_CLAUDE_CODE_TOOL_EXECUTION', true);
        }

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

            case 'create_post':
            case 'update_post':
            case 'get_posts':
            case 'get_post':
            case 'delete_post':
                $result = WP_Claude_Code_Content_Manager::execute_content_tool($function_name, $arguments);
                break;

            case 'read_attachment':
            case 'list_attachments':
                $result = WP_Claude_Code_File_Attachment::execute_attachment_tool($function_name, $arguments);
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
               "- **API Provider:** " . ($this->settings['api_provider'] ?? 'Not configured') . "\n" .
               "- **Claude API Key:** " . (!empty($this->settings['claude_api_key']) ? 'Yes' : 'No') . "\n" .
               "- **OpenAI API Key:** " . (!empty($this->settings['openai_api_key']) ? 'Yes' : 'No') . "\n" .
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
    
    /**
     * Test connection to the configured API provider
     */
    public function test_connection() {
        try {
            $test_message = "Hello! This is a connection test. Please respond with 'Connection successful'.";
            $result = $this->send_message($test_message);
            
            if (is_wp_error($result)) {
                return array(
                    'status' => 'error',
                    'message' => 'Connection failed: ' . $result->get_error_message(),
                    'provider' => $this->api_provider
                );
            } else {
                return array(
                    'status' => 'success',
                    'message' => 'Connection successful! Provider: ' . ucfirst($this->api_provider),
                    'response' => $result['content'] ?? 'No response content',
                    'provider' => $this->api_provider
                );
            }
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => 'Connection error: ' . $e->getMessage(),
                'provider' => $this->api_provider
            );
        }
    }
}