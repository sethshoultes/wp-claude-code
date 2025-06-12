<?php

class WP_Claude_Code_Database {
    
    public static function execute_query($query, $query_type) {
        global $wpdb;
        
        if (!self::is_safe_query($query, $query_type)) {
            return new WP_Error('unsafe_query', 'Query is not allowed for security reasons');
        }
        
        $query_type = strtoupper($query_type);
        
        switch ($query_type) {
            case 'SELECT':
                return self::execute_select($query);
                
            case 'UPDATE':
                return self::execute_update($query);
                
            case 'INSERT':
                return self::execute_insert($query);
                
            case 'DELETE':
                return self::execute_delete($query);
                
            default:
                return new WP_Error('invalid_query_type', 'Invalid query type: ' . $query_type);
        }
    }
    
    public static function get_site_info() {
        global $wpdb;
        
        $info = array(
            'database' => array(
                'name' => $wpdb->dbname,
                'host' => $wpdb->dbhost,
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
                'prefix' => $wpdb->prefix
            ),
            'tables' => array(
                'posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}"),
                'users' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}"),
                'comments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments}"),
                'options' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}")
            ),
            'content' => array(
                'published_posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'"),
                'published_pages' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page'"),
                'draft_posts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'draft'"),
                'pending_comments' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '0'")
            )
        );
        
        return $info;
    }
    
    public static function search_content($search_term, $post_types = array('post', 'page')) {
        global $wpdb;
        
        $search_term = '%' . $wpdb->esc_like($search_term) . '%';
        $post_types_placeholder = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";
        
        $query = $wpdb->prepare(
            "SELECT ID, post_title, post_type, post_status, post_date 
             FROM {$wpdb->posts} 
             WHERE (post_title LIKE %s OR post_content LIKE %s) 
             AND post_type IN ($post_types_placeholder)
             AND post_status = 'publish'
             ORDER BY post_date DESC
             LIMIT 50",
            $search_term,
            $search_term
        );
        
        return $wpdb->get_results($query);
    }
    
    private static function execute_select($query) {
        global $wpdb;
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return array(
            'rows' => $results,
            'count' => count($results),
            'query' => $query
        );
    }
    
    private static function execute_update($query) {
        global $wpdb;
        
        $affected_rows = $wpdb->query($query);
        
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return array(
            'affected_rows' => $affected_rows,
            'query' => $query
        );
    }
    
    private static function execute_insert($query) {
        global $wpdb;
        
        $result = $wpdb->query($query);
        
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return array(
            'inserted' => $result,
            'insert_id' => $wpdb->insert_id,
            'query' => $query
        );
    }
    
    private static function execute_delete($query) {
        global $wpdb;
        
        $affected_rows = $wpdb->query($query);
        
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error);
        }
        
        return array(
            'deleted_rows' => $affected_rows,
            'query' => $query
        );
    }
    
    private static function is_safe_query($query, $query_type) {
        global $wpdb;
        
        $query = trim(strtolower($query));
        $query_type = strtoupper($query_type);
        
        // Block dangerous operations
        $dangerous_keywords = array(
            'drop', 'truncate', 'alter', 'create', 'grant', 'revoke'
        );
        
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                return false;
            }
        }
        
        // Ensure query starts with expected type
        switch ($query_type) {
            case 'SELECT':
                return strpos($query, 'select') === 0;
                
            case 'UPDATE':
                return strpos($query, 'update') === 0;
                
            case 'INSERT':
                return strpos($query, 'insert') === 0;
                
            case 'DELETE':
                return strpos($query, 'delete') === 0;
                
            default:
                return false;
        }
    }
    
    public static function backup_database() {
        // This would implement database backup functionality
        // For now, return a placeholder
        return new WP_Error('not_implemented', 'Database backup feature not yet implemented');
    }
    
    public static function optimize_database() {
        global $wpdb;
        
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $optimized = array();
        
        foreach ($tables as $table) {
            $table_name = $table[0];
            $result = $wpdb->query("OPTIMIZE TABLE `$table_name`");
            $optimized[] = array(
                'table' => $table_name,
                'optimized' => $result !== false
            );
        }
        
        return $optimized;
    }
}