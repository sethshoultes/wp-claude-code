#!/bin/bash

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Define the path to the WordPress installation
WP_PATH="$(dirname "$(dirname "$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")")")"

# Run WP-CLI with the WordPress path
"$SCRIPT_DIR/wp" --path="$WP_PATH" "$@"

# If the command fails, print a helpful message
if [ $? -ne 0 ]; then
    echo ""
    echo "----------------------------------------------------"
    echo "🔍 Troubleshooting WP-CLI"
    echo "----------------------------------------------------"
    echo "If you're having issues, try:"
    echo "1. Checking if PHP is in your PATH"
    echo "2. Ensuring WordPress is properly installed"
    echo "3. Verifying file permissions"
    echo ""
    echo "For Local by Flywheel, you may need to use Local's"
    echo "built-in WP-CLI via the Local interface instead."
    echo "----------------------------------------------------"
fi