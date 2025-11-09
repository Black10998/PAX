#!/bin/bash
#
# PAX Support Pro - Master Release Automation Script
# Orchestrates the complete release process
#
# Usage: ./scripts/release.sh <new_version> [--skip-verify]
# Example: ./scripts/release.sh 4.0.7
#
# This script will:
# 1. Bump version in all required files
# 2. Build production ZIP package
# 3. Commit and push changes to GitHub
# 4. Create/update GitHub release with ZIP asset
# 5. Verify WordPress update detection
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Parse arguments
NEW_VERSION=""
SKIP_VERIFY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-verify)
            SKIP_VERIFY=true
            shift
            ;;
        *)
            NEW_VERSION="$1"
            shift
            ;;
    esac
done

# Check if version argument is provided
if [ -z "$NEW_VERSION" ]; then
    echo -e "${RED}Error: Version number required${NC}"
    echo ""
    echo "Usage: $0 <version> [--skip-verify]"
    echo "Example: $0 4.0.7"
    echo ""
    echo "Options:"
    echo "  --skip-verify    Skip WordPress update verification"
    exit 1
fi

# Validate version format
if ! [[ "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format${NC}"
    echo "Version must be in format: MAJOR.MINOR.PATCH (e.g., 4.0.7)"
    exit 1
fi

# Check prerequisites
echo -e "${CYAN}${BOLD}========================================${NC}"
echo -e "${CYAN}${BOLD}PAX Support Pro - Release Automation${NC}"
echo -e "${CYAN}${BOLD}========================================${NC}"
echo ""
echo -e "${YELLOW}Checking prerequisites...${NC}"

# Check git
if ! command -v git &> /dev/null; then
    echo -e "${RED}✗ git is not installed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ git${NC}"

# Check gh CLI
if ! command -v gh &> /dev/null; then
    echo -e "${RED}✗ GitHub CLI (gh) is not installed${NC}"
    echo "Install from: https://cli.github.com/"
    exit 1
fi
echo -e "${GREEN}✓ GitHub CLI${NC}"

# Check gh auth
if ! gh auth status &> /dev/null; then
    echo -e "${RED}✗ Not authenticated with GitHub CLI${NC}"
    echo "Run: gh auth login"
    exit 1
fi
echo -e "${GREEN}✓ GitHub authentication${NC}"

# Check for uncommitted changes
cd "$PLUGIN_DIR"
if ! git diff-index --quiet HEAD --; then
    echo ""
    echo -e "${YELLOW}⚠ Warning: You have uncommitted changes${NC}"
    git status --short
    echo ""
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Release cancelled"
        exit 0
    fi
fi

echo ""
echo -e "${CYAN}${BOLD}Release Version: $NEW_VERSION${NC}"
echo ""
read -p "Proceed with release? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Release cancelled"
    exit 0
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 1/5: Version Bump${NC}"
echo -e "${BLUE}========================================${NC}"
"$SCRIPT_DIR/bump-version.sh" "$NEW_VERSION" <<< "y"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 2/5: Build ZIP Package${NC}"
echo -e "${BLUE}========================================${NC}"
"$SCRIPT_DIR/build-release.sh" "$NEW_VERSION"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 3/5: Commit and Push Changes${NC}"
echo -e "${BLUE}========================================${NC}"

# Stage changes
git add pax-support-pro.php CHANGELOG.md

# Commit
COMMIT_MSG="Release v$NEW_VERSION

- Version bump to $NEW_VERSION
- Updated CHANGELOG.md
- Built release package

Co-authored-by: Ona <no-reply@ona.com>"

git commit -m "$COMMIT_MSG"
echo -e "${GREEN}✓ Changes committed${NC}"

# Push to main
git push origin main
echo -e "${GREEN}✓ Pushed to main branch${NC}"

# Create and push tag
TAG="v$NEW_VERSION"
git tag -a "$TAG" -m "PAX Support Pro $NEW_VERSION"
git push origin "$TAG"
echo -e "${GREEN}✓ Tag $TAG created and pushed${NC}"

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Step 4/5: Update GitHub Release${NC}"
echo -e "${BLUE}========================================${NC}"
"$SCRIPT_DIR/update-github-release.sh" "$NEW_VERSION"

if [ "$SKIP_VERIFY" = false ]; then
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}Step 5/5: Verify WordPress Update${NC}"
    echo -e "${BLUE}========================================${NC}"
    "$SCRIPT_DIR/verify-wp-update.sh"
else
    echo ""
    echo -e "${YELLOW}Skipping WordPress update verification${NC}"
fi

# Get release URL
RELEASE_URL=$(gh release view "$TAG" --json url --jq '.url')

echo ""
echo -e "${GREEN}${BOLD}========================================${NC}"
echo -e "${GREEN}${BOLD}Release Complete! 🎉${NC}"
echo -e "${GREEN}${BOLD}========================================${NC}"
echo ""
echo -e "${CYAN}Version:${NC} $NEW_VERSION"
echo -e "${CYAN}Tag:${NC} $TAG"
echo -e "${CYAN}Release URL:${NC} $RELEASE_URL"
echo ""
echo -e "${GREEN}Next steps:${NC}"
echo "  1. Review release at: $RELEASE_URL"
echo "  2. Test WordPress update detection"
echo "  3. Announce the release"
echo ""
