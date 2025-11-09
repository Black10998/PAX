#!/bin/bash
#
# PAX Support Pro - WordPress Update Verification Script
# Verifies that WordPress can detect the plugin update
#
# Usage: ./scripts/verify-wp-update.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_FILE="$PLUGIN_DIR/pax-support-pro.php"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PAX Support Pro - Update Verification${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Get current version
CURRENT_VERSION=$(grep -oP "Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+" "$PLUGIN_FILE" | head -1)
echo -e "${YELLOW}Current plugin version: $CURRENT_VERSION${NC}"

# Check if gh CLI is available
if ! command -v gh &> /dev/null; then
    echo -e "${RED}Error: GitHub CLI (gh) is not installed${NC}"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Get latest release from GitHub
echo ""
echo "Fetching latest GitHub release..."
LATEST_TAG=$(gh release view --json tagName --jq '.tagName' 2>/dev/null || echo "")

if [ -z "$LATEST_TAG" ]; then
    echo -e "${RED}Error: Could not fetch latest release${NC}"
    exit 1
fi

LATEST_VERSION="${LATEST_TAG#v}"
echo -e "${GREEN}✓ Latest GitHub release: $LATEST_VERSION${NC}"

# Get release info
RELEASE_URL=$(gh release view "$LATEST_TAG" --json url --jq '.url')
RELEASE_ASSETS=$(gh release view "$LATEST_TAG" --json assets --jq '.assets[].name')

echo ""
echo "Release Information:"
echo "  Tag: $LATEST_TAG"
echo "  URL: $RELEASE_URL"
echo ""
echo "Assets:"
echo "$RELEASE_ASSETS" | sed 's/^/  - /'

# Check if ZIP asset exists
ZIP_NAME="pax-support-pro-${LATEST_VERSION}.zip"
if echo "$RELEASE_ASSETS" | grep -q "$ZIP_NAME"; then
    echo ""
    echo -e "${GREEN}✓ ZIP asset found: $ZIP_NAME${NC}"
else
    echo ""
    echo -e "${RED}✗ ZIP asset not found: $ZIP_NAME${NC}"
    echo "Expected asset name: $ZIP_NAME"
    exit 1
fi

# Get ZIP download URL
ZIP_URL=$(gh release view "$LATEST_TAG" --json assets --jq ".assets[] | select(.name==\"$ZIP_NAME\") | .url")
echo -e "${GREEN}✓ ZIP download URL: $ZIP_URL${NC}"

# Test GitHub API endpoint (simulating plugin-update-checker)
echo ""
echo "Testing GitHub API endpoint..."
REPO_URL="https://github.com/Black10998/Black10998"
API_URL="https://api.github.com/repos/Black10998/Black10998/releases/latest"

echo "  API URL: $API_URL"

# Fetch release info via API
API_RESPONSE=$(curl -s "$API_URL")
API_TAG=$(echo "$API_RESPONSE" | grep -oP '"tag_name":\s*"\K[^"]+' | head -1)
API_VERSION="${API_TAG#v}"

if [ "$API_VERSION" = "$LATEST_VERSION" ]; then
    echo -e "${GREEN}✓ API returns correct version: $API_VERSION${NC}"
else
    echo -e "${RED}✗ API version mismatch${NC}"
    echo "  Expected: $LATEST_VERSION"
    echo "  Got: $API_VERSION"
    exit 1
fi

# Check if update is available
echo ""
echo "Update Detection:"
if [ "$CURRENT_VERSION" = "$LATEST_VERSION" ]; then
    echo -e "${YELLOW}⚠ Plugin is up to date (no update available)${NC}"
    echo "  Current: $CURRENT_VERSION"
    echo "  Latest: $LATEST_VERSION"
else
    # Compare versions
    if [ "$(printf '%s\n' "$LATEST_VERSION" "$CURRENT_VERSION" | sort -V | head -n1)" = "$CURRENT_VERSION" ]; then
        echo -e "${GREEN}✓ Update available!${NC}"
        echo "  Current: $CURRENT_VERSION"
        echo "  Latest: $LATEST_VERSION"
    else
        echo -e "${RED}✗ Current version is newer than latest release${NC}"
        echo "  Current: $CURRENT_VERSION"
        echo "  Latest: $LATEST_VERSION"
    fi
fi

# Summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Verification Complete${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Summary:"
echo "  ✓ GitHub release exists"
echo "  ✓ ZIP asset is available"
echo "  ✓ API endpoint is accessible"
echo "  ✓ Version information is correct"
echo ""
echo "WordPress Update Checker Status:"
echo "  The plugin-update-checker library will:"
echo "  1. Check GitHub API every 12 hours"
echo "  2. Compare $LATEST_VERSION with installed version"
echo "  3. Show update notification if newer version available"
echo "  4. Download ZIP from: $ZIP_URL"
echo ""
echo "To test in WordPress:"
echo "  1. Go to Dashboard > Updates"
echo "  2. Click 'Check Again' button"
echo "  3. Look for PAX Support Pro update notification"
echo ""
