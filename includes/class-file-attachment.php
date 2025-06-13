<?php
/**
 * File Attachment Handler Class
 * 
 * This class handles file attachments in the chat interface.
 */

class WP_Claude_Code_File_Attachment {
    
    private static $instance = null;
    private $upload_dir;
    private $max_file_size;
    private $allowed_types;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_dir = trailingslashit($upload_dir['basedir']) . 'claude-code-attachments';
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
        $this->allowed_types = array(
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'text/plain', 'text/csv',
            'application/json',
            'application/pdf',
            'application/zip',
            'text/html', 'text/css', 'text/javascript',
            'application/javascript',
            'application/x-sql'
        );
        
        add_action('wp_ajax_claude_code_upload_file', array($this, 'handle_file_upload'));
        add_action('wp_ajax_claude_code_get_attachments', array($this, 'handle_get_attachments'));
        add_action('wp_ajax_claude_code_delete_attachment', array($this, 'handle_delete_attachment'));
        add_action('wp_ajax_claude_code_get_attachment_content', array($this, 'handle_get_attachment_content'));
        
        // Create upload directory if it doesn't exist
        $this->ensure_upload_directory();
    }
    
    /**
     * Ensure upload directory exists
     */
    private function ensure_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "<files ~ \"\\.(txt|json|css|js|sql)$\">\n";
            $htaccess_content .= "    allow from all\n";
            $htaccess_content .= "</files>\n";
            
            file_put_contents(trailingslashit($this->upload_dir) . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Handle file upload
     */
    public function handle_file_upload() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $file = $_FILES['file'];
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        
        // Validate file
        $validation = $this->validate_file($file);
        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message());
        }
        
        // Save file
        $result = $this->save_file($file, $conversation_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload failed');
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return new WP_Error('file_too_large', 'File size exceeds 10MB limit');
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            return new WP_Error('invalid_file_type', 'File type not allowed: ' . $mime_type);
        }
        
        // Additional security checks
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'csv', 'json', 'pdf', 'zip', 'html', 'css', 'js', 'sql');
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return new WP_Error('invalid_extension', 'File extension not allowed: ' . $file_extension);
        }
        
        return true;
    }
    
    /**
     * Save uploaded file
     */
    private function save_file($file, $conversation_id) {
        $file_name = sanitize_file_name($file['name']);
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $file_base = pathinfo($file_name, PATHINFO_FILENAME);
        
        // Generate unique filename
        $unique_id = uniqid();
        $new_filename = $file_base . '_' . $unique_id . '.' . $file_extension;
        $file_path = trailingslashit($this->upload_dir) . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            return new WP_Error('save_failed', 'Failed to save uploaded file');
        }
        
        // Store file metadata
        $attachment_data = array(
            'id' => $unique_id,
            'original_name' => $file_name,
            'filename' => $new_filename,
            'file_path' => $file_path,
            'file_size' => $file['size'],
            'mime_type' => $file['type'],
            'conversation_id' => $conversation_id,
            'user_id' => get_current_user_id(),
            'uploaded_at' => current_time('mysql'),
            'is_image' => $this->is_image_file($file['type'])
        );
        
        $this->save_attachment_metadata($attachment_data);
        
        // Prepare response
        $response = array(
            'id' => $unique_id,
            'name' => $file_name,
            'size' => $file['size'],
            'type' => $file['type'],
            'url' => $this->get_attachment_url($unique_id),
            'is_image' => $attachment_data['is_image']
        );
        
        // If it's a text file, include preview content
        if ($this->is_text_file($file['type'])) {
            $content = file_get_contents($file_path);
            if ($content !== false) {
                $response['preview'] = substr($content, 0, 500);
                $response['content'] = $content;
            }
        }
        
        return $response;
    }
    
    /**
     * Check if file is an image
     */
    private function is_image_file($mime_type) {
        return strpos($mime_type, 'image/') === 0;
    }
    
    /**
     * Check if file is a text file
     */
    private function is_text_file($mime_type) {
        $text_types = array('text/', 'application/json', 'application/javascript');
        
        foreach ($text_types as $type) {
            if (strpos($mime_type, $type) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Save attachment metadata
     */
    private function save_attachment_metadata($data) {
        $attachments = get_option('claude_code_attachments', array());
        $attachments[$data['id']] = $data;
        update_option('claude_code_attachments', $attachments);
    }
    
    /**
     * Get attachment metadata
     */
    private function get_attachment_metadata($attachment_id) {
        $attachments = get_option('claude_code_attachments', array());
        return isset($attachments[$attachment_id]) ? $attachments[$attachment_id] : null;
    }
    
    /**
     * Get attachment URL
     */
    private function get_attachment_url($attachment_id) {
        $metadata = $this->get_attachment_metadata($attachment_id);
        if (!$metadata) {
            return null;
        }
        
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $metadata['file_path']);
        return $upload_dir['baseurl'] . $relative_path;
    }
    
    /**
     * Handle get attachments request
     */
    public function handle_get_attachments() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $conversation_id = sanitize_text_field($_POST['conversation_id'] ?? '');
        $user_id = get_current_user_id();
        
        $attachments = get_option('claude_code_attachments', array());
        $filtered_attachments = array();
        
        foreach ($attachments as $attachment) {
            if ($attachment['user_id'] == $user_id) {
                if (empty($conversation_id) || $attachment['conversation_id'] == $conversation_id) {
                    $filtered_attachments[] = array(
                        'id' => $attachment['id'],
                        'name' => $attachment['original_name'],
                        'size' => $attachment['file_size'],
                        'type' => $attachment['mime_type'],
                        'uploaded_at' => $attachment['uploaded_at'],
                        'conversation_id' => $attachment['conversation_id'],
                        'is_image' => $attachment['is_image'],
                        'url' => $this->get_attachment_url($attachment['id'])
                    );
                }
            }
        }
        
        // Sort by upload date (newest first)
        usort($filtered_attachments, function($a, $b) {
            return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
        });
        
        wp_send_json_success(array('attachments' => $filtered_attachments));
    }
    
    /**
     * Handle delete attachment request
     */
    public function handle_delete_attachment() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $attachment_id = sanitize_text_field($_POST['attachment_id']);
        $user_id = get_current_user_id();
        
        $metadata = $this->get_attachment_metadata($attachment_id);
        
        if (!$metadata || $metadata['user_id'] != $user_id) {
            wp_send_json_error('Attachment not found or access denied');
        }
        
        // Delete file
        if (file_exists($metadata['file_path'])) {
            unlink($metadata['file_path']);
        }
        
        // Remove from metadata
        $attachments = get_option('claude_code_attachments', array());
        unset($attachments[$attachment_id]);
        update_option('claude_code_attachments', $attachments);
        
        wp_send_json_success(array('message' => 'Attachment deleted'));
    }
    
    /**
     * Handle get attachment content request
     */
    public function handle_get_attachment_content() {
        check_ajax_referer('claude_code_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $attachment_id = sanitize_text_field($_POST['attachment_id']);
        $user_id = get_current_user_id();
        
        $metadata = $this->get_attachment_metadata($attachment_id);
        
        if (!$metadata || $metadata['user_id'] != $user_id) {
            wp_send_json_error('Attachment not found or access denied');
        }
        
        if (!$this->is_text_file($metadata['mime_type'])) {
            wp_send_json_error('File is not a text file');
        }
        
        $content = file_get_contents($metadata['file_path']);
        
        if ($content === false) {
            wp_send_json_error('Failed to read file content');
        }
        
        wp_send_json_success(array(
            'content' => $content,
            'filename' => $metadata['original_name'],
            'mime_type' => $metadata['mime_type']
        ));
    }
    
    /**
     * Get file attachment tools for Claude API
     */
    public static function get_attachment_tools() {
        return array(
            'read_attachment' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'read_attachment',
                    'description' => 'Read the content of an uploaded file attachment',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'attachment_id' => array(
                                'type' => 'string',
                                'description' => 'The ID of the attachment to read'
                            )
                        ),
                        'required' => array('attachment_id')
                    )
                )
            ),
            'list_attachments' => array(
                'type' => 'function',
                'function' => array(
                    'name' => 'list_attachments',
                    'description' => 'List all file attachments for the current conversation or user',
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => array(
                            'conversation_id' => array(
                                'type' => 'string',
                                'description' => 'Optional conversation ID to filter attachments'
                            )
                        )
                    )
                )
            )
        );
    }
    
    /**
     * Execute attachment tools
     */
    public static function execute_attachment_tool($tool_name, $args) {
        $instance = self::get_instance();
        
        switch ($tool_name) {
            case 'read_attachment':
                return $instance->read_attachment_content($args['attachment_id']);
            case 'list_attachments':
                return $instance->list_attachments($args['conversation_id'] ?? '');
            default:
                return new WP_Error('invalid_tool', 'Unknown attachment tool: ' . $tool_name);
        }
    }
    
    /**
     * Read attachment content for AI processing
     */
    public function read_attachment_content($attachment_id) {
        $user_id = get_current_user_id();
        $metadata = $this->get_attachment_metadata($attachment_id);
        
        if (!$metadata || $metadata['user_id'] != $user_id) {
            return new WP_Error('not_found', 'Attachment not found or access denied');
        }
        
        if ($this->is_text_file($metadata['mime_type'])) {
            $content = file_get_contents($metadata['file_path']);
            
            if ($content === false) {
                return new WP_Error('read_failed', 'Failed to read file content');
            }
            
            return array(
                'success' => true,
                'filename' => $metadata['original_name'],
                'content' => $content,
                'size' => $metadata['file_size'],
                'type' => 'text',
                'mime_type' => $metadata['mime_type']
            );
        } elseif ($this->is_image_file($metadata['mime_type'])) {
            // Convert image to base64 for AI processing
            $image_data = $this->encode_image_for_ai($metadata['file_path'], $metadata['mime_type']);
            
            if (is_wp_error($image_data)) {
                return $image_data;
            }
            
            return array(
                'success' => true,
                'filename' => $metadata['original_name'],
                'type' => 'image',
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['file_size'],
                'base64_data' => $image_data['base64'],
                'media_type' => $image_data['media_type'],
                'url' => $this->get_attachment_url($attachment_id)
            );
        } else {
            return array(
                'success' => true,
                'filename' => $metadata['original_name'],
                'type' => 'binary',
                'mime_type' => $metadata['mime_type'],
                'size' => $metadata['file_size'],
                'message' => 'Binary file detected. Content cannot be read as text.'
            );
        }
    }
    
    /**
     * Encode image for AI processing
     */
    private function encode_image_for_ai($file_path, $mime_type) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Image file not found');
        }
        
        // Check file size (limit to 10MB for API efficiency)
        $file_size = filesize($file_path);
        if ($file_size > 10 * 1024 * 1024) {
            return new WP_Error('file_too_large', 'Image file too large for processing');
        }
        
        // Read and encode the image
        $image_data = file_get_contents($file_path);
        if ($image_data === false) {
            return new WP_Error('read_failed', 'Failed to read image file');
        }
        
        $base64_data = base64_encode($image_data);
        
        return array(
            'base64' => $base64_data,
            'media_type' => $mime_type
        );
    }
    
    /**
     * List attachments for AI processing
     */
    public function list_attachments($conversation_id = '') {
        $user_id = get_current_user_id();
        $attachments = get_option('claude_code_attachments', array());
        $result = array();
        
        foreach ($attachments as $attachment) {
            if ($attachment['user_id'] == $user_id) {
                if (empty($conversation_id) || $attachment['conversation_id'] == $conversation_id) {
                    $result[] = array(
                        'id' => $attachment['id'],
                        'name' => $attachment['original_name'],
                        'size' => $attachment['file_size'],
                        'type' => $attachment['mime_type'],
                        'uploaded_at' => $attachment['uploaded_at'],
                        'conversation_id' => $attachment['conversation_id'],
                        'is_readable' => $this->is_text_file($attachment['mime_type']),
                        'is_image' => $attachment['is_image']
                    );
                }
            }
        }
        
        return array(
            'success' => true,
            'attachments' => $result,
            'total' => count($result)
        );
    }
}