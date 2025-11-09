#!/bin/bash
#
# PAX Support Pro - GitHub Release Update Script
# Updates GitHub release with new ZIP asset and sets as latest
#
# Usage: ./scripts/update-github-release.sh <version>
# Example: ./scripts/update-github-release.sh 4.0.7
#
# Requirements: gh CLI (GitHub CLI) must be installed and authenticated
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PLUGIN_DIR/build"
PLUGIN_SLUG="pax-support-pro"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if version argument is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Version number required${NC}"
    echo "Usage: $0 <version>"
    echo "Example: $0 4.0.7"
    exit 1
fi

VERSION="$1"
TAG="v$VERSION"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$BUILD_DIR/$ZIP_NAME"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PAX Support Pro - GitHub Release Updater${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if gh CLI is installed
if ! command -v gh &> /dev/null; then
    echo -e "${RED}Error: GitHub CLI (gh) is not installed${NC}"
    echo "Install it from: https://cli.github.com/"
    exit 1
fi

# Check if authenticated
if ! gh auth status &> /dev/null; then
    echo -e "${RED}Error: Not authenticated with GitHub CLI${NC}"
    echo "Run: gh auth login"
    exit 1
fi

# Check if ZIP exists
if [ ! -f "$ZIP_PATH" ]; then
    echo -e "${RED}Error: ZIP file not found: $ZIP_PATH${NC}"
    echo "Run: ./scripts/build-release.sh $VERSION"
    exit 1
fi

echo -e "${YELLOW}Version: $VERSION${NC}"
echo -e "${YELLOW}Tag: $TAG${NC}"
echo -e "${YELLOW}ZIP: $ZIP_NAME${NC}"
echo ""

# Check if release exists
echo "Checking if release exists..."
if gh release view "$TAG" &> /dev/null; then
    echo -e "${GREEN}✓ Release $TAG exists${NC}"
    
    # Delete old asset if exists
    echo ""
    echo "Checking for existing assets..."
    EXISTING_ASSETS=$(gh release view "$TAG" --json assets --jq '.assets[].name')
    
    if echo "$EXISTING_ASSETS" | grep -q "$ZIP_NAME"; then
        echo -e "${YELLOW}Found existing asset: $ZIP_NAME${NC}"
        echo "Deleting old asset..."
        gh release delete-asset "$TAG" "$ZIP_NAME" --yes
        echo -e "${GREEN}✓ Old asset deleted${NC}"
    fi
    
    # Upload new asset
    echo ""
    echo "Uploading new asset..."
    gh release upload "$TAG" "$ZIP_PATH" --clobber
    echo -e "${GREEN}✓ Asset uploaded${NC}"
    
    # Mark as latest release
    echo ""
    echo "Setting as latest release..."
    gh release edit "$TAG" --latest
    echo -e "${GREEN}✓ Marked as latest${NC}"
    
else
    echo -e "${YELLOW}Release $TAG does not exist${NC}"
    echo "Creating new release..."
    
    # Extract changelog for this version
    CHANGELOG_FILE="$PLUGIN_DIR/CHANGELOG.md"
    RELEASE_NOTES=""
    
    if [ -f "$CHANGELOG_FILE" ]; then
        # Extract notes between ## [$VERSION] and next ## [
        RELEASE_NOTES=$(awk "/^## \[$VERSION\]/,/^## \[/" "$CHANGELOG_FILE" | sed '1d;$d' | sed '/^$/d')
    fi
    
    if [ -z "$RELEASE_NOTES" ]; then
        RELEASE_NOTES="Release $VERSION"
    fi
    
    # Create release with asset
    gh release create "$TAG" "$ZIP_PATH" \
        --title "PAX Support Pro $VERSION" \
        --notes "$RELEASE_NOTES" \
        --latest
    
    echo -e "${GREEN}✓ Release created${NC}"
fi

# Get release URL
RELEASE_URL=$(gh release view "$TAG" --json url --jq '.url')

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}GitHub Release Updated!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Release URL: $RELEASE_URL"
echo "Asset: $ZIP_NAME"
echo ""
echo "Next steps:"
echo "  1. Verify release at: $RELEASE_URL"
echo "  2. Run: ./scripts/verify-wp-update.sh"
echo ""
