#!/bin/bash
# Universal WP-CLI Installation Script
# Works on: Local by Flywheel, shared hosting, VPS, dedicated servers, Docker, etc.

echo "🚀 Universal WP-CLI Installation Script"
echo "======================================="

# Detect environment
detect_environment() {
    if [ -f /.dockerenv ]; then
        echo "🐳 Docker container detected"
        ENV_TYPE="docker"
    elif [ -d "/Applications/Local.app" ] || [ -n "$LOCAL_SITE_PATH" ]; then
        echo "💻 Local by Flywheel detected"
        ENV_TYPE="local"
    elif [ -n "$CPANEL_USER" ] || [ -d "/home/*/public_html" ]; then
        echo "🌐 Shared hosting (cPanel-like) detected"
        ENV_TYPE="shared"
    elif [ -f "/etc/debian_version" ] || [ -f "/etc/ubuntu_version" ]; then
        echo "🐧 Debian/Ubuntu server detected"
        ENV_TYPE="debian"
    elif [ -f "/etc/redhat-release" ] || [ -f "/etc/centos-release" ]; then
        echo "🔴 RedHat/CentOS server detected"
        ENV_TYPE="redhat"
    else
        echo "❓ Unknown environment - using generic installation"
        ENV_TYPE="generic"
    fi
}

detect_environment

# Check if WP-CLI is already installed
check_existing_installation() {
    echo -e "\n🔍 Checking for existing WP-CLI installation..."
    
    if command -v wp &> /dev/null; then
        echo "✅ WP-CLI is already installed!"
        wp --version
        echo "Location: $(which wp)"
        echo "No installation needed."
        exit 0
    fi
    
    # Check common locations
    local wp_locations=(
        "/usr/local/bin/wp"
        "/usr/bin/wp"
        "/opt/wp-cli/wp-cli.phar"
        "../wp-cli.phar"
        "./wp-cli.phar"
    )
    
    for location in "${wp_locations[@]}"; do
        if [ -f "$location" ]; then
            echo "✅ Found WP-CLI at: $location"
            php "$location" --version
            echo "No installation needed."
            exit 0
        fi
    done
    
    echo "❌ WP-CLI not found. Proceeding with installation..."
}

check_existing_installation

# Download WP-CLI
download_wp_cli() {
    echo -e "\n📥 Downloading WP-CLI..."
    
    # Try curl first, then wget
    if command -v curl &> /dev/null; then
        curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar
    elif command -v wget &> /dev/null; then
        wget https://raw.githubusercontent.com/wp-cli/wp-cli/v2.8.1/wp-cli.phar
    else
        echo "❌ Error: Neither curl nor wget found. Cannot download WP-CLI."
        echo "Please install curl or wget and try again."
        exit 1
    fi
    
    # Check if download was successful
    if [ ! -f wp-cli.phar ]; then
        echo "❌ Error: Failed to download WP-CLI"
        exit 1
    fi
    
    echo "✅ WP-CLI downloaded successfully!"
}

# Verify WP-CLI integrity
verify_wp_cli() {
    echo -e "\n🔍 Verifying WP-CLI integrity..."
    
    # Test the phar file
    php wp-cli.phar --info > /dev/null 2>&1
    
    if [ $? -eq 0 ]; then
        echo "✅ WP-CLI file is valid!"
        chmod +x wp-cli.phar
    else
        echo "❌ Error: Downloaded WP-CLI file is corrupted"
        rm -f wp-cli.phar
        exit 1
    fi
}

# Install WP-CLI based on environment
install_wp_cli() {
    echo -e "\n🔧 Installing WP-CLI for $ENV_TYPE environment..."
    
    case $ENV_TYPE in
        "shared")
            # Shared hosting - install locally
            mv wp-cli.phar ../wp-cli.phar
            echo "✅ WP-CLI installed locally at $(pwd)/../wp-cli.phar"
            echo "Usage: php ../wp-cli.phar [command]"
            echo "Tip: Create an alias in your .bashrc: alias wp='php $(pwd)/../wp-cli.phar'"
            ;;
            
        "docker")
            # Docker - try global, fallback to local
            if [ -w "/usr/local/bin" ]; then
                mv wp-cli.phar /usr/local/bin/wp
                echo "✅ WP-CLI installed globally at /usr/local/bin/wp"
            else
                mv wp-cli.phar ../wp-cli.phar
                echo "✅ WP-CLI installed locally at $(pwd)/../wp-cli.phar"
                echo "Usage: php ../wp-cli.phar [command]"
            fi
            ;;
            
        "debian"|"redhat")
            # VPS/Dedicated server - try global with sudo
            if command -v sudo &> /dev/null && sudo -n true 2>/dev/null; then
                echo "Installing WP-CLI globally (using sudo)..."
                sudo mv wp-cli.phar /usr/local/bin/wp
                echo "✅ WP-CLI installed globally at /usr/local/bin/wp"
            elif [ -w "/usr/local/bin" ]; then
                mv wp-cli.phar /usr/local/bin/wp
                echo "✅ WP-CLI installed globally at /usr/local/bin/wp"
            else
                echo "⚠️  Cannot install globally. Installing locally..."
                mv wp-cli.phar ../wp-cli.phar
                echo "✅ WP-CLI installed locally at $(pwd)/../wp-cli.phar"
                echo "Usage: php ../wp-cli.phar [command]"
                echo "Tip: To install globally, run: sudo mv ../wp-cli.phar /usr/local/bin/wp"
            fi
            ;;
            
        "local"|"generic"|*)
            # Local by Flywheel or generic - try multiple approaches
            if command -v sudo &> /dev/null; then
                echo "Attempting global installation (may require password)..."
                if sudo mv wp-cli.phar /usr/local/bin/wp 2>/dev/null; then
                    echo "✅ WP-CLI installed globally at /usr/local/bin/wp"
                else
                    echo "⚠️  Global installation failed. Installing locally..."
                    mv wp-cli.phar ../wp-cli.phar
                    echo "✅ WP-CLI installed locally at $(pwd)/../wp-cli.phar"
                    echo "Usage: php ../wp-cli.phar [command]"
                fi
            else
                mv wp-cli.phar ../wp-cli.phar
                echo "✅ WP-CLI installed locally at $(pwd)/../wp-cli.phar"
                echo "Usage: php ../wp-cli.phar [command]"
            fi
            ;;
    esac
}

# Test final installation
test_installation() {
    echo -e "\n🧪 Testing WP-CLI installation..."
    
    if command -v wp &> /dev/null; then
        echo "✅ WP-CLI is working globally!"
        wp --version
        echo "You can now use: wp [command]"
    elif [ -f ../wp-cli.phar ]; then
        echo "✅ WP-CLI is working locally!"
        php ../wp-cli.phar --version
        echo "You can use: php ../wp-cli.phar [command]"
        
        # Create a convenience script
        cat > ../wp << 'EOF'
#!/bin/bash
php "$(dirname "$0")/wp-cli.phar" "$@"
EOF
        chmod +x ../wp
        echo "✅ Created convenience script at ../wp"
        echo "You can now use: ../wp [command]"
    else
        echo "❌ Installation verification failed!"
        exit 1
    fi
    
    echo -e "\n🎉 WP-CLI installation completed successfully!"
    echo "Environment: $ENV_TYPE"
    echo "WordPress Path: $(pwd)"
}

# Main installation flow
main() {
    download_wp_cli
    verify_wp_cli
    install_wp_cli
    test_installation
    
    echo -e "\n📚 Next Steps:"
    echo "1. Test WP-CLI: wp core version"
    echo "2. See available commands: wp help"
    echo "3. Your Claude Code plugin will now detect and use WP-CLI!"
    echo -e "\n💡 Tip: If you're on shared hosting, add this to your .bashrc:"
    echo "   alias wp='php \$HOME/public_html/wp-cli.phar'"
}

# Run the installation
main