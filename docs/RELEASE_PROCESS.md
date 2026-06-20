# Release Process

**How to Create a New Release of WP Speakeasy**

This guide explains the automated release process using GitHub Actions.

---

## Quick Start

Creating a new release is as simple as pushing a version tag:

```bash
# 1. Update version and commit changes
# 2. Create and push a tag
git tag v1.0.1
git push origin v1.0.1

# 3. GitHub automatically builds and releases!
```

That's it! GitHub Actions will:
- ✅ Install production dependencies
- ✅ Create optimized ZIP file
- ✅ Attach ZIP to GitHub release
- ✅ Generate release notes

---

## Detailed Release Process

### Step 1: Prepare Release

Update version numbers and changelog:

```bash
# Edit version in main plugin file
vim wp-speakeasy.php
# Change line: define( 'SPEAKEASY_VERSION', '1.0.1' );
# Change line: Version: 1.0.1

# Update CHANGELOG.md
vim CHANGELOG.md
# Add new version section under [Unreleased]
```

**Example CHANGELOG.md update:**

```markdown
## [Unreleased]

---

## [1.0.1] — 2026-06-21

### Fixed
- Fixed LAP schema loading for custom templates
- Improved error handling in API reporter

### Changed
- Optimized module initialization order
```

### Step 2: Commit Changes

```bash
git add wp-speakeasy.php CHANGELOG.md
git commit -m "Bump version to 1.0.1"
git push origin main
```

### Step 3: Create Release Tag

```bash
# Create tag (use semantic versioning: vMAJOR.MINOR.PATCH)
git tag v1.0.1

# Or create annotated tag with message
git tag -a v1.0.1 -m "Release version 1.0.1"

# Push tag to GitHub (triggers release workflow)
git push origin v1.0.1
```

### Step 4: Monitor GitHub Action

1. Go to your GitHub repository
2. Click **Actions** tab
3. Watch the "Build and Release" workflow
4. Wait for green checkmark (takes ~2-3 minutes)

### Step 5: Verify Release

1. Go to **Releases** tab on GitHub
2. You should see `v1.0.1` release with:
   - ✅ `wp-speakeasy.zip` attachment
   - ✅ Auto-generated release notes
   - ✅ Installation instructions

---

## What the GitHub Action Does

The automated workflow ([.github/workflows/release.yml](../.github/workflows/release.yml)):

### 1. **Triggered On**
```yaml
on:
  push:
    tags:
      - 'v*.*.*'
```
Any tag matching `v1.0.0`, `v2.3.4`, etc.

### 2. **Build Steps**

**a) Setup Environment**
- Ubuntu latest
- PHP 8.1
- Composer cache for faster builds

**b) Install Dependencies**
```bash
composer install --no-dev --optimize-autoloader
```
Only production dependencies, optimized autoloader.

**c) Update Version**
Automatically updates version in `wp-speakeasy.php` from tag.

**d) Create ZIP**
```bash
zip -r wp-speakeasy.zip . -x [dev files]
```
Excludes:
- `.git/` and `.github/`
- `tests/`
- `docs/`
- `PRPs/`
- All `.md` files except those needed for WordPress.org
- Development configs (phpunit.xml, phpstan.neon)

**e) Create GitHub Release**
- Uploads `wp-speakeasy.zip`
- Generates release notes
- Adds installation instructions

**f) Upload Artifact**
Keeps ZIP for 90 days for manual download.

---

## Release Types

### Patch Release (v1.0.X)

**When:** Bug fixes, minor improvements
**Example:** v1.0.0 → v1.0.1

```bash
# Update version to 1.0.1
git tag v1.0.1
git push origin v1.0.1
```

### Minor Release (v1.X.0)

**When:** New features, backward compatible
**Example:** v1.0.1 → v1.1.0

```bash
# Update version to 1.1.0
git tag v1.1.0
git push origin v1.1.0
```

### Major Release (vX.0.0)

**When:** Breaking changes, major overhaul
**Example:** v1.1.0 → v2.0.0

```bash
# Update version to 2.0.0
git tag v2.0.0
git push origin v2.0.0
```

---

## Pre-release Versions

For beta or release candidate versions:

```bash
# Beta release
git tag v1.1.0-beta.1
git push origin v1.1.0-beta.1

# Release candidate
git tag v1.1.0-rc.1
git push origin v1.1.0-rc.1
```

**Note:** The GitHub Action triggers on all `v*.*.*` tags, including pre-releases.
Mark these as "Pre-release" in GitHub UI if needed.

---

## Troubleshooting

### Build Failed

**Check the GitHub Actions log:**
1. Go to **Actions** tab
2. Click the failed workflow
3. Click **build** job
4. Expand failed step

**Common issues:**

**Composer dependency error:**
```
Problem 1
  - Package requires ext-xml
```
**Solution:** Already handled in workflow (PHP extensions installed).

**Permission denied:**
```
Error: Resource not accessible by integration
```
**Solution:** Check repository settings → Actions → Workflow permissions.

### Release Not Created

**Check GitHub token permissions:**
1. Repository Settings → Actions → General
2. Workflow permissions → **Read and write permissions**
3. Save changes

### ZIP File Missing Dependencies

**Verify Composer installed production deps:**

```bash
# Manually test locally
composer install --no-dev
zip -r test.zip . -x "*.git*"
unzip -l test.zip | grep vendor
# Should show vendor/ directory with dependencies
```

---

## Manual Release (Fallback)

If GitHub Actions fails, create release manually:

```bash
# 1. Build ZIP locally
composer install --no-dev --optimize-autoloader

zip -r wp-speakeasy.zip . \
  -x "*.git*" \
  -x "*.github/*" \
  -x "tests/*" \
  -x "docs/*" \
  -x "PRPs/*" \
  -x "*.md"

# 2. Create GitHub release via CLI
gh release create v1.0.1 \
  wp-speakeasy.zip \
  --title "v1.0.1" \
  --notes "Release notes here"

# Or upload via GitHub web UI
# Releases → Draft a new release → Upload wp-speakeasy.zip
```

---

## After Release

### 1. Test Auto-Update

On a WordPress site with the plugin:

```bash
# Force update check
wp transient delete update_plugins
wp plugin list

# Should show update available
wp plugin update wp-speakeasy
```

### 2. Update Documentation

If README or docs changed, ensure they're updated on:
- GitHub repository (main branch)
- WordPress.org plugin page (if applicable)

### 3. Announce Release

- Email users (if applicable)
- Update company documentation
- Notify team via Slack/Discord

### 4. Monitor Deployment

If using API reporting:
- Check backend dashboard
- Monitor update success rate
- Watch for error reports

---

## Version Numbering Guide

Follow [Semantic Versioning 2.0.0](https://semver.org/):

**Format:** `MAJOR.MINOR.PATCH`

### MAJOR (X.0.0)
Increment when making **incompatible API changes**

Examples:
- Removing a module
- Changing database schema (breaking)
- Removing public functions
- WordPress minimum version increase

### MINOR (0.X.0)
Increment when adding **backward-compatible functionality**

Examples:
- Adding a new module
- New schema fields
- New API endpoints
- New configuration options

### PATCH (0.0.X)
Increment for **backward-compatible bug fixes**

Examples:
- Fixing a bug
- Security patches
- Performance improvements
- Documentation updates

---

## Changelog Format

Follow [Keep a Changelog](https://keepachangelog.com/) format:

```markdown
## [Unreleased]

---

## [1.1.0] — 2026-06-25

### Added
- New SEO optimization module
- Support for custom LAP template variants
- Email notifications for failed updates

### Changed
- Improved error messages in API reporter
- Optimized database queries in LAP meta module

### Fixed
- Fixed timezone issue in health check timestamps
- Corrected schema loading for multi-site installations

### Security
- Updated Plugin Update Checker to v5.3.0

---

## [1.0.1] — 2026-06-21

### Fixed
- Fixed LAP schema loading for custom templates
```

---

## Example: Complete Release

Here's a complete example of releasing v1.1.0:

```bash
# 1. Create feature branch
git checkout -b feature/seo-module

# 2. Implement feature
# ... code changes ...

# 3. Update version and changelog
vim wp-speakeasy.php
# Change: define( 'SPEAKEASY_VERSION', '1.1.0' );
# Change: Version: 1.1.0

vim CHANGELOG.md
# Add v1.1.0 section

# 4. Commit changes
git add .
git commit -m "Add SEO optimization module

Features:
- Meta tag management
- Sitemap generation
- Schema.org markup

Closes #42"

# 5. Merge to main
git checkout main
git merge feature/seo-module
git push origin main

# 6. Create and push tag
git tag v1.1.0
git push origin v1.1.0

# 7. Wait for GitHub Action to complete
# Watch at: https://github.com/speakeasy/wp-speakeasy/actions

# 8. Verify release
# Visit: https://github.com/speakeasy/wp-speakeasy/releases

# 9. Test on staging site
wp plugin update wp-speakeasy --version=1.1.0

# 10. Announce to team
echo "v1.1.0 released! 🎉" | send-to-slack
```

---

## Automation Checklist

Before each release, verify:

- [ ] All tests pass locally (`composer test`)
- [ ] Code quality checks pass (`composer phpcs`, `composer phpstan`)
- [ ] Version updated in `wp-speakeasy.php` (two places)
- [ ] CHANGELOG.md updated with version and changes
- [ ] Committed and pushed to main branch
- [ ] Tag created with `v` prefix (e.g., `v1.0.1`)
- [ ] Tag pushed to GitHub
- [ ] GitHub Action completed successfully
- [ ] Release appears with ZIP attachment
- [ ] Downloaded and tested ZIP file

---

## GitHub Action Status Badge

Add to README.md:

```markdown
![Build Status](https://github.com/speakeasy/wp-speakeasy/actions/workflows/release.yml/badge.svg)
```

Shows green ✅ if latest release built successfully.

---

## Support

For issues with the release process:

- **GitHub Actions:** Check workflow logs in Actions tab
- **Repository Issues:** https://github.com/speakeasy/wp-speakeasy/issues
- **Email:** dev@speakeasy.com

---

## Next Steps

After mastering the release process:

1. **Set up WordPress.org deployment** (if publishing publicly)
2. **Add automated testing** (PHPUnit in GitHub Actions)
3. **Implement changelog generator** (auto-generate from commits)
4. **Create deployment dashboard** (track rollout across all sites)

---

## Changelog

### v1.0.0 (2026-06-20)
- Initial release process documentation
- GitHub Action for automatic builds
- Semantic versioning guidelines
