<?php

class WP_Claude_Code_Filesystem {
    
    public static function read_file($file_path) {
        $full_path = self::resolve_path($file_path);
        
        if (!self::is_safe_path($full_path)) {
            return new WP_Error('unsafe_path', 'Access to this file is not allowed');
        }
        
        if (!file_exists($full_path)) {
            return new WP_Error('file_not_found', 'File not found: ' . $file_path);
        }
        
        if (!is_readable($full_path)) {
            return new WP_Error('file_not_readable', 'File is not readable: ' . $file_path);
        }
        
        $content = file_get_contents($full_path);
        
        if ($content === false) {
            return new WP_Error('read_error', 'Failed to read file: ' . $file_path);
        }
        
        return array(
            'path' => $file_path,
            'content' => $content,
            'size' => filesize($full_path),
            'modified' => date('Y-m-d H:i:s', filemtime($full_path))
        );
    }
    
    public static function edit_file($file_path, $content, $create_backup = true) {
        $full_path = self::resolve_path($file_path);
        
        if (!self::is_safe_path($full_path)) {
            return new WP_Error('unsafe_path', 'Access to this file is not allowed');
        }
        
        if (!self::can_edit_file($full_path)) {
            return new WP_Error('edit_not_allowed', 'This file cannot be edited for security reasons');
        }
        
        // Create backup if requested and file exists
        if ($create_backup && file_exists($full_path)) {
            $backup_result = self::create_backup($full_path);
            if (is_wp_error($backup_result)) {
                return $backup_result;
            }
        }
        
        // Validate content for PHP files
        if (pathinfo($full_path, PATHINFO_EXTENSION) === 'php') {
            $validation_result = self::validate_php_syntax($content);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }
        }
        
        // Write the file
        $result = file_put_contents($full_path, $content);
        
        if ($result === false) {
            return new WP_Error('write_error', 'Failed to write file: ' . $file_path);
        }
        
        return array(
            'path' => $file_path,
            'bytes_written' => $result,
            'backup_created' => $create_backup && isset($backup_result)
        );
    }
    
    public static function list_directory($dir_path, $filter = null) {
        $full_path = self::resolve_path($dir_path);
        
        if (!self::is_safe_path($full_path)) {
            return new WP_Error('unsafe_path', 'Access to this directory is not allowed');
        }
        
        if (!is_dir($full_path)) {
            return new WP_Error('not_directory', 'Path is not a directory: ' . $dir_path);
        }
        
        $files = array();
        $iterator = new DirectoryIterator($full_path);
        
        foreach ($iterator as $file) {
            if ($file->isDot()) continue;
            
            $file_info = array(
                'name' => $file->getFilename(),
                'type' => $file->isDir() ? 'directory' : 'file',
                'size' => $file->isFile() ? $file->getSize() : null,
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                'permissions' => substr(sprintf('%o', $file->getPerms()), -4)
            );
            
            if ($filter && !fnmatch($filter, $file_info['name'])) {
                continue;
            }
            
            $files[] = $file_info;
        }
        
        return $files;
    }
    
    private static function resolve_path($path) {
        // Handle relative paths from WordPress root
        if (!path_is_absolute($path)) {
            $path = ABSPATH . ltrim($path, '/');
        }
        
        return realpath($path) ?: $path;
    }
    
    private static function is_safe_path($path) {
        $wp_root = realpath(ABSPATH);
        $path = realpath($path) ?: $path;
        
        // Must be within WordPress directory
        if (strpos($path, $wp_root) !== 0) {
            return false;
        }
        
        // Block sensitive files
        $blocked_files = array(
            'wp-config.php',
            '.htaccess',
            'wp-admin/install.php',
            'wp-admin/setup-config.php'
        );
        
        foreach ($blocked_files as $blocked) {
            if (strpos($path, $wp_root . '/' . $blocked) === 0) {
                return false;
            }
        }
        
        return true;
    }
    
    private static function can_edit_file($path) {
        // Additional checks for file editing
        $filename = basename($path);
        
        // Block core WordPress files
        if (strpos($path, ABSPATH . 'wp-includes/') === 0) {
            return false;
        }
        
        if (strpos($path, ABSPATH . 'wp-admin/') === 0) {
            return false;
        }
        
        return true;
    }
    
    private static function create_backup($file_path) {
        $backup_dir = WP_CLAUDE_CODE_PLUGIN_PATH . 'backups/';
        
        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }
        
        $relative_path = str_replace(ABSPATH, '', $file_path);
        $backup_name = date('Y-m-d_H-i-s') . '_' . str_replace('/', '_', $relative_path);
        $backup_path = $backup_dir . $backup_name;
        
        if (!copy($file_path, $backup_path)) {
            return new WP_Error('backup_failed', 'Failed to create backup');
        }
        
        return $backup_path;
    }
    
    private static function validate_php_syntax($code) {
        $temp_file = tempnam(sys_get_temp_dir(), 'wp_claude_code_syntax_check');
        file_put_contents($temp_file, $code);
        
        $output = array();
        $return_var = 0;
        exec("php -l $temp_file 2>&1", $output, $return_var);
        
        unlink($temp_file);
        
        if ($return_var !== 0) {
            return new WP_Error('syntax_error', 'PHP syntax error: ' . implode('\n', $output));
        }
        
        return true;
    }
}