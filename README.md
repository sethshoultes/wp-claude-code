# WP Claude Code

A Claude Code-style AI assistant interface for WordPress development and management. Provides intelligent WordPress development assistance with AI-powered tools for file management, database operations, content management, and site administration.

## 🚀 Features

### 🤖 AI-Powered WordPress Assistant
- **Intelligent Chat Interface** - Claude Code-style conversation UI
- **WordPress-Aware Tools** - File operations, database queries, WP-CLI integration
- **Context-Aware Responses** - Understands WordPress structure and best practices
- **Real-time Assistance** - Get help with development tasks as you work

### 🛠️ WordPress Development Tools
- **File Management** - Read, edit, and analyze theme/plugin files with safety checks
- **Database Operations** - Safe query execution with validation and security
- **Content Management** - Create, edit, and manage posts, pages, and custom content
- **Site Information** - Comprehensive site analysis and health checks

### 🔧 Technical Capabilities
- **WP-CLI Integration** - Execute WordPress CLI commands (with native fallbacks)
- **Security First** - Rate limiting, permission checks, and audit logging
- **Universal Compatibility** - Works on shared hosting, VPS, Docker, Local development
- **Direct API Support** - Native integration with ChatGPT and Claude APIs

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Admin access to WordPress
- OpenAI API key or Anthropic Claude API key

## 🔧 Installation

1. **Download and Extract**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/yourusername/wp-claude-code.git
   ```

2. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "WP Claude Code" and click "Activate"

3. **Configure Settings**
   - Go to Claude Code → Settings
   - Choose your API provider (OpenAI or Claude Direct)
   - Enter your API key from OpenAI or Anthropic
   - Select your preferred model
   - Enable desired tools (file operations, database, WP-CLI)

## ⚙️ Configuration

### API Provider Setup
The plugin works with direct API connections to OpenAI and Anthropic:

#### OpenAI Configuration
1. Get your API key from [platform.openai.com](https://platform.openai.com)
2. Go to Claude Code → Settings
3. Select "Direct OpenAI API" as provider
4. Enter your OpenAI API key
5. Choose from available models (GPT-4o, GPT-4o Mini, GPT-4 Turbo)

#### Claude Configuration  
1. Get your API key from [console.anthropic.com](https://console.anthropic.com)
2. Go to Claude Code → Settings
3. Select "Direct Claude API" as provider
4. Enter your Claude API key
5. Choose from available models (Claude 3.5 Sonnet, Claude 3 Opus, etc.)

### Settings Options
- **API Provider** - Choose between OpenAI or Claude Direct
- **API Key** - Your OpenAI or Anthropic API key
- **Model** - AI model to use (GPT-4o, Claude 3.5 Sonnet, etc.)
- **Max Tokens** - Response length limit
- **Enabled Tools** - Choose which WordPress tools to enable

### Features
- **Image Analysis** - All models support vision capabilities
- **Automatic Formatting** - Images optimized for each API provider
- **Direct Integration** - No proxy required, faster responses

## 🎯 Usage

### Chat Interface
Access the main interface at **WordPress Admin → Claude Code**

Example interactions:
```
You: "Show me the active theme information"
AI Assistant: [Displays detailed theme info, files, features]

You: "List all installed plugins"  
AI Assistant: [Shows plugin status, versions, descriptions]

You: "Create a new page called 'About Us'"
AI Assistant: [Creates page and provides edit link]

You: "Analyze this screenshot of my website"
AI Assistant: [Analyzes uploaded image with detailed feedback]

You: "Check database performance"
AI Assistant: [Analyzes database health and provides recommendations]
```

### Available Commands
- **Site Information** - "Show WordPress site info"
- **Theme Management** - "What theme is active?" / "List theme files"
- **Plugin Management** - "List plugins" / "Show plugin status"
- **Content Management** - "List posts" / "Create new page"
- **Database Operations** - "Check database status" / "Show table sizes"
- **File Operations** - "Read functions.php" / "Edit style.css"
- **Image Analysis** - Upload screenshots, designs, or error pages for analysis

### Image Analysis Features
- **Website Screenshots** - Analyze layout, design, and UX issues
- **Error Page Analysis** - Diagnose problems from error screenshots
- **Design Review** - Get feedback on visual elements and styling
- **Accessibility Assessment** - Identify accessibility improvements
- **Mobile Responsiveness** - Review mobile layout and functionality

## 🛡️ Security Features

### Built-in Protections
- **File Access Control** - Blocks access to sensitive files (wp-config.php, etc.)
- **Permission Validation** - Respects WordPress user capabilities
- **SQL Injection Prevention** - Validates and sanitizes all database queries
- **Rate Limiting** - Prevents API abuse (60 requests/hour per user)
- **Audit Logging** - Tracks all file and database operations

### Safe Operations
- **Automatic Backups** - Creates backups before file modifications
- **Syntax Validation** - Checks PHP syntax before saving files
- **Read-Only Mode** - Option to disable write operations
- **Staging Support** - Test changes on staging sites first

## 🔌 WP-CLI Integration

### Automatic Installation
The plugin includes a universal WP-CLI installer that works on:
- **Shared Hosting** (cPanel, Plesk, etc.)
- **VPS/Dedicated Servers** (Ubuntu, CentOS, etc.)
- **Docker Containers**
- **Local Development** (Local by Flywheel, MAMP, etc.)
- **Cloud Hosting** (AWS, DigitalOcean, etc.)

### Native Fallbacks
If WP-CLI is unavailable, the plugin automatically uses WordPress native functions:
- `wp core version` → `global $wp_version`
- `wp plugin list` → `get_plugins()`
- `wp theme list` → `wp_get_themes()`
- `wp post list` → `get_posts()`

## 🌍 Environment Support

### Development Environments
- ✅ **Local by Flywheel**
- ✅ **MAMP/XAMPP/WAMP**
- ✅ **Docker Containers**
- ✅ **Vagrant/VirtualBox**

### Hosting Platforms
- ✅ **Shared Hosting** (Bluehost, SiteGround, etc.)
- ✅ **VPS/Cloud** (DigitalOcean, AWS, Linode)
- ✅ **Managed WordPress** (WP Engine, Kinsta)
- ✅ **Traditional Hosting** (cPanel, Plesk)

### Server Requirements
- **PHP:** 7.4+ (8.0+ recommended)
- **WordPress:** 5.0+ (6.0+ recommended)
- **MySQL:** 5.6+ or MariaDB 10.0+
- **Memory:** 128MB+ (256MB+ recommended)

## 🚀 Advanced Usage

### Custom Tools
The plugin's tool system is extensible. You can add custom tools by:

1. **Creating Tool Classes**
   ```php
   class My_Custom_Tool extends WP_Claude_Code_Tool {
       public function execute($arguments) {
           // Your custom logic here
           return "Tool result";
       }
   }
   ```

2. **Registering Tools**
   ```php
   add_filter('wp_claude_code_tools', function($tools) {
       $tools['my_custom_tool'] = new My_Custom_Tool();
       return $tools;
   });
   ```

### API Integration
The plugin provides REST API endpoints for external integration:

```bash
# Get site information
curl -X POST /wp-json/claude-code/v1/site-info \
  -H "Authorization: Bearer YOUR_TOKEN"

# Execute chat request
curl -X POST /wp-json/claude-code/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "List all plugins"}'
```

## 🐛 Troubleshooting

### Common Issues

**API Authentication Errors (401)**
- Check LiteLLM endpoint URL
- Verify API key configuration
- Test proxy connection manually

**Permission Denied Errors**
- Ensure user has `manage_options` capability
- Check file system permissions
- Verify WordPress user roles

**WP-CLI Not Found**
- Use the built-in WP-CLI installer
- Check server PATH environment
- Use native WordPress functions (automatic fallback)

**Empty Tool Responses**
- Check debug logs in `/logs/php/error.log`
- Verify database connectivity
- Test individual tools via debug page

### Debug Tools
- **Debug Page** - WordPress Admin → Claude Code → Debug Config
- **Connection Test** - Settings page → Test LiteLLM Connection
- **WP-CLI Installer** - WordPress Admin → Claude Code → Install WP-CLI

## 📁 Project Structure

```
wp-claude-code/
├── wp-claude-code.php          # Main plugin file
├── includes/                   # Core classes
│   ├── class-admin.php         # Admin interface
│   ├── class-api.php           # REST API endpoints
│   ├── class-claude-api.php    # LiteLLM integration
│   ├── class-database.php      # Database operations
│   ├── class-filesystem.php    # File operations
│   ├── class-security.php      # Security & permissions
│   └── class-wp-cli-bridge.php # WP-CLI integration
├── assets/                     # Frontend assets
│   ├── css/admin.css          # Admin styles
│   └── js/admin.js            # Admin JavaScript
├── install-wp-cli.sh          # Universal WP-CLI installer
├── debug-config.php           # Debug utilities
└── README.md                  # This file
```

## 🤝 Contributing

1. **Fork the Repository**
2. **Create Feature Branch** (`git checkout -b feature/amazing-feature`)
3. **Commit Changes** (`git commit -m 'Add amazing feature'`)
4. **Push to Branch** (`git push origin feature/amazing-feature`)
5. **Open Pull Request**

### Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/wp-claude-code.git

# Install in WordPress
cp -r wp-claude-code /path/to/wordpress/wp-content/plugins/

# Activate plugin and configure settings
```

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🔗 Links

- **Documentation** - [Wiki](https://github.com/yourusername/wp-claude-code/wiki)
- **Issues** - [GitHub Issues](https://github.com/yourusername/wp-claude-code/issues)
- **Support** - [Discussions](https://github.com/yourusername/wp-claude-code/discussions)

## 🙏 Acknowledgments

- **Claude AI** - For providing the intelligent assistant capabilities
- **WordPress Community** - For the robust platform and ecosystem
- **LiteLLM** - For the flexible AI model proxy framework
- **Contributors** - Everyone who helps improve this project

---

**Made with ❤️ for the WordPress community**