#!/bin/bash
#
# Quick Cookie Updater - Paste Mode
# Works with bookmarklet output!
#
# Usage: 
#   1. Use bookmarklet to copy cookies
#   2. Run: ./update-cookies-paste.sh
#   3. Paste the copied config section
#   4. Press Ctrl+D when done

set -eo pipefail

CONFIG_FILE="/workspaces/rss-bridge/config/config.ini.php"
TEMP_FILE=$(mktemp)

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo ""
echo "=========================================="
echo "  Quick Cookie Update (Paste Mode)"
echo "=========================================="
echo ""
echo -e "${BLUE}Instructions:${NC}"
echo "1. Use the bookmarklet to copy cookies"
echo "2. Paste the copied text below"
echo "3. Press Ctrl+D (or Cmd+D on Mac) when done"
echo ""
echo -e "${YELLOW}Paste your [PerplexityBridge] config section:${NC}"
echo ""

# Read pasted content
cat > "$TEMP_FILE"

# Validate it has the required token
if grep -q "session_token.*=" "$TEMP_FILE"; then
    echo ""
    echo -e "${GREEN}✓ Configuration received${NC}"
    
    # Backup current config
    if [ -f "$CONFIG_FILE" ]; then
        BACKUP_DIR="/workspaces/rss-bridge/config/backups"
        mkdir -p "$BACKUP_DIR"
        cp "$CONFIG_FILE" "$BACKUP_DIR/config.$(date +%Y%m%d_%H%M%S).ini.php"
        
        # Remove existing PerplexityBridge section
        sed -i '/^\[PerplexityBridge\]/,/^$/d' "$CONFIG_FILE"
    fi
    
    # Add new section
    echo "" >> "$CONFIG_FILE"
    cat "$TEMP_FILE" >> "$CONFIG_FILE"
    
    echo -e "${GREEN}✅ Configuration updated!${NC}"
    echo ""
    echo "Restart RSS-Bridge to apply changes."
else
    echo ""
    echo -e "${RED}✗ Invalid configuration${NC}"
    echo "Make sure you pasted the [PerplexityBridge] section"
    rm "$TEMP_FILE"
    exit 1
fi

rm "$TEMP_FILE"
