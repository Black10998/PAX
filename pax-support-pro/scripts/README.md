# PAX Support Pro - Release Scripts

## Quick Reference

### One-Command Release
```bash
./scripts/release.sh 4.0.7
```

### Individual Scripts

| Script | Purpose | Usage |
|--------|---------|-------|
| `release.sh` | Full automation | `./scripts/release.sh 4.0.7` |
| `bump-version.sh` | Update version | `./scripts/bump-version.sh 4.0.7` |
| `build-release.sh` | Build ZIP | `./scripts/build-release.sh 4.0.7` |
| `update-github-release.sh` | Update GitHub | `./scripts/update-github-release.sh 4.0.7` |
| `verify-wp-update.sh` | Verify updates | `./scripts/verify-wp-update.sh` |

## Prerequisites

```bash
# Install GitHub CLI
# macOS: brew install gh
# Linux: https://cli.github.com/

# Authenticate
gh auth login
```

## What Each Script Does

### `release.sh` - Master Script
Runs all scripts in sequence:
1. Bump version
2. Build ZIP
3. Commit & push
4. Update GitHub release
5. Verify WordPress update

### `bump-version.sh`
Updates version in:
- Plugin header `Version:`
- Plugin header `@version`
- `PAX_SUP_VER` constant
- CHANGELOG.md

### `build-release.sh`
Creates ZIP package:
- Includes: admin, includes, public, rest, plugin-update-checker
- Excludes: backups, tests, scripts, build files
- Output: `build/pax-support-pro-<version>.zip`

### `update-github-release.sh`
Manages GitHub release:
- Creates/updates release for tag `v<version>`
- Uploads ZIP asset
- Marks as latest release
- Extracts notes from CHANGELOG.md

### `verify-wp-update.sh`
Verifies update detection:
- Checks GitHub API
- Verifies ZIP asset
- Compares versions
- Tests update availability

## Common Workflows

### Patch Release (Bug Fix)
```bash
./scripts/release.sh 4.0.7
```

### Minor Release (New Features)
```bash
./scripts/release.sh 4.1.0
```

### Major Release (Breaking Changes)
```bash
./scripts/release.sh 5.0.0
```

## Troubleshooting

### "gh: command not found"
Install GitHub CLI: https://cli.github.com/

### "Not authenticated"
Run: `gh auth login`

### "ZIP file not found"
Run: `./scripts/build-release.sh <version>`

### "Permission denied"
Run: `chmod +x scripts/*.sh`

## Full Documentation

See: `/workspaces/Black10998/RELEASE_AUTOMATION.md`

## Version Format

Semantic Versioning: `MAJOR.MINOR.PATCH`
- Patch: Bug fixes (4.0.6 → 4.0.7)
- Minor: New features (4.0.7 → 4.1.0)
- Major: Breaking changes (4.1.0 → 5.0.0)
