# Changelog

All notable changes to WP Claude Code will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-12

### Added
- Initial release of WP Claude Code plugin
- Claude Code-style chat interface for WordPress development
- LiteLLM proxy integration for AI model support
- WordPress-aware file operations with safety checks
- Database query tools with security validation
- WP-CLI integration with native WordPress fallbacks
- Universal WP-CLI installer for all hosting environments
- Comprehensive site information and analysis tools
- Theme and plugin management capabilities
- Content management (posts, pages, custom post types)
- Security features: rate limiting, audit logging, permission checks
- Admin interface with real-time chat
- REST API endpoints for external integration
- Debug tools and configuration helpers
- Auto-detection of MemberPress AI Assistant configuration

### Security
- File access restrictions (blocks wp-config.php, core files)
- SQL injection prevention with query validation
- User permission validation based on WordPress capabilities
- Rate limiting (60 requests per hour per user)
- Audit logging for all operations
- Automatic file backups before modifications
- PHP syntax validation for code changes

### Tools
- `wp_site_info` - Comprehensive WordPress site information
- `wp_theme_info` - Active theme details and file structure
- `wp_database_status` - Database health and performance metrics
- `wp_content_list` - Post, page, and content management
- `wp_file_read` - Read WordPress files with context awareness
- `wp_file_edit` - Edit files with backup and validation
- `wp_cli_exec` - Execute WP-CLI commands safely
- `wp_db_query` - Safe database operations

### Environments Supported
- Local by Flywheel
- Shared hosting (cPanel, Plesk)
- VPS and dedicated servers
- Docker containers
- Cloud hosting (AWS, DigitalOcean, etc.)
- Managed WordPress hosts

### Known Issues
- WP-CLI installer requires manual execution on some shared hosts
- Large database analysis may timeout on resource-limited hosts
- File operations limited to wp-content directory for security

### Technical Details
- Minimum PHP 7.4 (8.0+ recommended)
- WordPress 5.0+ (6.0+ recommended)
- MySQL 5.6+ or MariaDB 10.0+
- Memory requirement: 128MB+ (256MB+ recommended)