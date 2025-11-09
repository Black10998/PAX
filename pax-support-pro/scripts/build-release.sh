#!/bin/bash
#
# PAX Support Pro - Release Build Script
# Creates a production-ready ZIP package
#
# Usage: ./scripts/build-release.sh <version>
# Example: ./scripts/build-release.sh 4.0.7
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
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$BUILD_DIR/$ZIP_NAME"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PAX Support Pro - Release Builder${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "${YELLOW}Version: $VERSION${NC}"
echo -e "${YELLOW}Output: $ZIP_NAME${NC}"
echo ""

# Create build directory
mkdir -p "$BUILD_DIR"

# Clean previous builds
if [ -f "$ZIP_PATH" ]; then
    echo -e "${YELLOW}Removing previous build...${NC}"
    rm -f "$ZIP_PATH"
fi

echo -e "${GREEN}Building release package...${NC}"
echo ""

# Create temporary directory for packaging
TEMP_DIR=$(mktemp -d)
PACKAGE_DIR="$TEMP_DIR/$PLUGIN_SLUG"
mkdir -p "$PACKAGE_DIR"

# Files and directories to include
INCLUDE_ITEMS=(
    "admin"
    "includes"
    "public"
    "rest"
    "plugin-update-checker"
    "pax-support-pro.php"
    "README.txt"
    "CHANGELOG.md"
)

# Files and directories to exclude
EXCLUDE_PATTERNS=(
    "*.backup"
    "*.bak"
    "*.tmp"
    "*~"
    ".DS_Store"
    "Thumbs.db"
    "*.log"
    "node_modules"
    ".git"
    ".gitignore"
    "scripts"
    "build"
    "tests"
    "test-*.html"
    "*.zip"
)

echo "Copying files..."

# Copy included items
for item in "${INCLUDE_ITEMS[@]}"; do
    if [ -e "$PLUGIN_DIR/$item" ]; then
        cp -r "$PLUGIN_DIR/$item" "$PACKAGE_DIR/"
        echo "  ✓ $item"
    else
        echo -e "  ${YELLOW}⚠ $item not found (skipping)${NC}"
    fi
done

# Remove excluded patterns
echo ""
echo "Cleaning up excluded files..."
for pattern in "${EXCLUDE_PATTERNS[@]}"; do
    find "$PACKAGE_DIR" -name "$pattern" -exec rm -rf {} + 2>/dev/null || true
done
echo "  ✓ Cleanup complete"

# Create ZIP archive
echo ""
echo "Creating ZIP archive..."
cd "$TEMP_DIR"
zip -r -q "$ZIP_PATH" "$PLUGIN_SLUG"

# Cleanup temp directory
rm -rf "$TEMP_DIR"

# Get file size
FILE_SIZE=$(du -h "$ZIP_PATH" | cut -f1)

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Build Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Package: $ZIP_NAME"
echo "Size: $FILE_SIZE"
echo "Location: $ZIP_PATH"
echo ""
echo "Next steps:"
echo "  1. Test the ZIP package"
echo "  2. Run: ./scripts/update-github-release.sh $VERSION"
echo ""
