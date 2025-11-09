#!/bin/bash
#
# PAX Support Pro - Version Bump Script
# Automatically updates version in all required locations
#
# Usage: ./scripts/bump-version.sh <new_version>
# Example: ./scripts/bump-version.sh 4.0.7
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"
PLUGIN_FILE="$PLUGIN_DIR/pax-support-pro.php"
CHANGELOG_FILE="$PLUGIN_DIR/CHANGELOG.md"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if version argument is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Version number required${NC}"
    echo "Usage: $0 <version>"
    echo "Example: $0 4.0.7"
    exit 1
fi

NEW_VERSION="$1"

# Validate version format (semantic versioning)
if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format${NC}"
    echo "Version must be in format: MAJOR.MINOR.PATCH (e.g., 4.0.7)"
    exit 1
fi

# Get current version from plugin file
CURRENT_VERSION=$(grep -oP "Version:\s*\K[0-9]+\.[0-9]+\.[0-9]+" "$PLUGIN_FILE" | head -1)

if [ -z "$CURRENT_VERSION" ]; then
    echo -e "${RED}Error: Could not detect current version${NC}"
    exit 1
fi

echo -e "${YELLOW}Current version: $CURRENT_VERSION${NC}"
echo -e "${YELLOW}New version: $NEW_VERSION${NC}"
echo ""

# Confirm version bump
read -p "Proceed with version bump? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Version bump cancelled"
    exit 0
fi

echo -e "${GREEN}Updating version in plugin files...${NC}"

# 1. Update plugin header Version
sed -i "s/\* Version: $CURRENT_VERSION/\* Version: $NEW_VERSION/" "$PLUGIN_FILE"
echo "✓ Updated plugin header Version"

# 2. Update @version tag
sed -i "s/@version $CURRENT_VERSION/@version $NEW_VERSION/" "$PLUGIN_FILE"
echo "✓ Updated @version tag"

# 3. Update PAX_SUP_VER constant
sed -i "s/define( 'PAX_SUP_VER', '$CURRENT_VERSION' );/define( 'PAX_SUP_VER', '$NEW_VERSION' );/" "$PLUGIN_FILE"
echo "✓ Updated PAX_SUP_VER constant"

# 4. Add entry to CHANGELOG.md
CURRENT_DATE=$(date +%Y-%m-%d)
CHANGELOG_ENTRY="## [$NEW_VERSION] - $CURRENT_DATE\n\n### Changes\n- Version bump to $NEW_VERSION\n\n"

# Insert after the header (after "## [" line)
sed -i "/^## \[/i $CHANGELOG_ENTRY" "$CHANGELOG_FILE"
echo "✓ Updated CHANGELOG.md"

echo ""
echo -e "${GREEN}Version bump complete!${NC}"
echo ""
echo "Updated files:"
echo "  - $PLUGIN_FILE"
echo "  - $CHANGELOG_FILE"
echo ""
echo "Next steps:"
echo "  1. Edit CHANGELOG.md to add detailed changes"
echo "  2. Run: ./scripts/build-release.sh $NEW_VERSION"
echo "  3. Run: ./scripts/update-github-release.sh $NEW_VERSION"
echo ""
