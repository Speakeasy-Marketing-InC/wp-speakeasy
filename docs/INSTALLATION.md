# Installation Guide

**WP Speakeasy WordPress Plugin**

Complete guide to installing and configuring WP Speakeasy on your WordPress sites.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation Methods](#installation-methods)
  - [Method 1: WordPress Admin (Recommended)](#method-1-wordpress-admin-recommended)
  - [Method 2: FTP/SFTP Upload](#method-2-ftpsftp-upload)
  - [Method 3: WP-CLI](#method-3-wp-cli)
  - [Method 4: Git Clone (Development)](#method-4-git-clone-development)
- [Configuration](#configuration)
- [Verification](#verification)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Server Requirements

- **WordPress:** 5.6 or higher
- **PHP:** 7.4 or higher (8.0+ recommended)
- **HTTPS:** Required for Application Passwords feature
- **Write Permissions:** Plugin directory must be writable

### Check Your Environment

```bash
# Via WP-CLI
wp core version
wp cli info

# Or check in WordPress admin
# Dashboard → Updates → WordPress Version
```

---

## Installation Methods

### Method 1: WordPress Admin (Recommended)

This is the easiest method for most users.

#### Step 1: Download Plugin ZIP

First, you need to create a ZIP file of the plugin (without the development files):

```bash
# From your development machine
cd /home/kithinji/Documents/speakeasy/wp-speakeasy

# Install Composer dependencies (production only)
composer install --no-dev --optimize-autoloader

# Create a clean ZIP file (excludes dev files)
zip -r wp-speakeasy.zip . \
  -x "*.git*" \
  -x "node_modules/*" \
  -x "tests/*" \
  -x "docs/*" \
  -x "reports/*" \
  -x "PRPs/*" \
  -x "*.md" \
  -x "phpunit.xml*" \
  -x "phpstan.neon" \
  -x "composer.lock" \
  -x ".gitignore" \
  -x ".llmignore"

# This creates wp-speakeasy.zip
```

#### Step 2: Upload to WordPress

1. **Login to WordPress Admin**
   - Navigate to your WordPress site
   - Go to `wp-admin`
   - Enter your credentials

2. **Go to Plugins Page**
   - Click **Plugins** → **Add New** in the left sidebar

3. **Upload Plugin**
   - Click **Upload Plugin** button at the top
   - Click **Choose File**
   - Select `wp-speakeasy.zip`
   - Click **Install Now**

4. **Activate Plugin**
   - Wait for upload to complete
   - Click **Activate Plugin**
   - You should see a success message

#### Step 3: Verify Installation

- Go to **Settings** → **WP Speakeasy**
- You should see the plugin dashboard showing:
  - Plugin version (1.0.0)
  - Module status (2 modules active)
  - System diagnostics

---

### Method 2: FTP/SFTP Upload

Use this method if you have FTP/SFTP access to your server.

#### Step 1: Prepare Plugin Files

```bash
# Install production dependencies
cd /home/kithinji/Documents/speakeasy/wp-speakeasy
composer install --no-dev --optimize-autoloader

# The plugin is now ready to upload
# Key directories to include:
# - wp-speakeasy.php (main file)
# - includes/
# - modules/
# - admin/
# - vendor/ (Composer dependencies)
```

#### Step 2: Connect via FTP/SFTP

```bash
# Using SFTP command line
sftp username@yoursite.com

# Or use FTP clients:
# - FileZilla (Windows/Mac/Linux)
# - Cyberduck (Mac)
# - WinSCP (Windows)
```

#### Step 3: Upload to Plugins Directory

```bash
# Navigate to plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Create plugin directory
mkdir wp-speakeasy

# Upload all files
put -r /home/kithinji/Documents/speakeasy/wp-speakeasy/* wp-speakeasy/
```

#### Step 4: Activate via WordPress Admin

1. Go to **Plugins** → **Installed Plugins**
2. Find **WP Speakeasy** in the list
3. Click **Activate**

---

### Method 3: WP-CLI

Use this method for command-line installation (fastest for multiple sites).

#### Prerequisites

```bash
# Check if WP-CLI is installed
wp --version

# If not installed, install it:
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp
```

#### Installation Steps

```bash
# Navigate to WordPress root directory
cd /path/to/wordpress

# Method A: Install from local directory
wp plugin install /home/kithinji/Documents/speakeasy/wp-speakeasy --activate

# Method B: Install from ZIP file
wp plugin install /path/to/wp-speakeasy.zip --activate

# Verify installation
wp plugin list | grep wp-speakeasy
```

#### Expected Output

```
wp-speakeasy  1.0.0  active  WP Speakeasy
```

---

### Method 4: Git Clone (Development)

Use this method for development and testing.

#### Step 1: Clone Repository

```bash
# Navigate to WordPress plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Clone the repository
git clone https://github.com/speakeasy/wp-speakeasy.git

# Or if you have local access
ln -s /home/kithinji/Documents/speakeasy/wp-speakeasy ./wp-speakeasy
```

#### Step 2: Install Dependencies

```bash
cd wp-speakeasy

# Install production dependencies
composer install --no-dev

# Or install all dependencies (including dev tools)
composer install
```

#### Step 3: Activate Plugin

```bash
# Via WP-CLI
wp plugin activate wp-speakeasy

# Or via WordPress admin as described in Method 1
```

---

## Configuration

After installation, configure the plugin in `wp-config.php`.

### Basic Configuration (Optional)

The plugin works immediately without configuration. The settings below are **optional**.

### Auto-Update Configuration

To enable automatic updates from GitHub:

```php
// wp-config.php

/**
 * WP Speakeasy: GitHub Auto-Updater
 *
 * Enables automatic plugin updates from GitHub releases.
 * Remove these lines to disable auto-updates.
 */
define( 'SPEAKEASY_GITHUB_REPO', 'speakeasy/wp-speakeasy' );

// Only needed for private repositories
define( 'SPEAKEASY_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );
```

**To get a GitHub token:**
1. Go to GitHub → Settings → Developer settings
2. Personal access tokens → Generate new token
3. Select scopes: `repo` (full control)
4. Copy token and paste above

### API Reporting Configuration

To enable centralized monitoring:

```php
// wp-config.php

/**
 * WP Speakeasy: API Reporting
 *
 * Sends health checks and status updates to your monitoring API.
 * Remove these lines to disable reporting.
 */
define( 'SPEAKEASY_API_ENDPOINT', 'https://api.speakeasy.com/wp-plugin' );
define( 'SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxxxxxxxxxx' );
```

See [API_INTEGRATION.md](API_INTEGRATION.md) for full API documentation.

### Complete Configuration Example

```php
<?php
/**
 * WordPress Configuration File
 */

// ... existing WordPress config ...

/**
 * WP Speakeasy Configuration (Optional)
 *
 * All settings below are optional. The plugin works without them.
 */

// GitHub Auto-Updater (optional)
define( 'SPEAKEASY_GITHUB_REPO', 'speakeasy/wp-speakeasy' );
define( 'SPEAKEASY_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );

// API Reporting (optional)
define( 'SPEAKEASY_API_ENDPOINT', 'https://api.speakeasy.com/wp-plugin' );
define( 'SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxxxxxxxxxx' );

/* That's all, stop editing! Happy publishing. */
```

---

## Verification

### 1. Check Plugin Status

**Via WordPress Admin:**
- Go to **Plugins** → **Installed Plugins**
- Look for **WP Speakeasy** with status **Active**

**Via WP-CLI:**
```bash
wp plugin list --status=active | grep wp-speakeasy
```

### 2. Check Application Passwords

**Via WordPress Admin:**
1. Go to **Users** → **Profile**
2. Scroll to **Application Passwords** section
3. You should see the section (even if it was previously disabled)

**Via WP-CLI:**
```bash
wp eval "var_dump(wp_is_application_passwords_available());"
# Should output: bool(true)
```

### 3. Check Admin Dashboard

1. Go to **Settings** → **WP Speakeasy**
2. Verify you see:
   - ✅ **Status:** Plugin Version 1.0.0
   - ✅ **Modules:** 2 modules active
     - Application Passwords Enabler (Active)
     - LAP Meta Fields (Active)
   - ✅ **Diagnostics:**
     - Application Passwords: Available
     - REST API: Accessible
     - Meta Fields Registered: X fields

### 4. Test REST API

Generate an Application Password and test REST API access:

```bash
# 1. Generate Application Password
# Go to Users → Profile → Application Passwords
# Enter name "Test" → Add New
# Copy the generated password (format: xxxx xxxx xxxx xxxx)

# 2. Test REST API
curl -X GET "https://yoursite.com/wp-json/wp/v2/pages?per_page=1&context=edit" \
  -u "admin:xxxx xxxx xxxx xxxx"

# Should return JSON with page data including 'meta' field
```

### 5. Check Error Logs

```bash
# Check WordPress debug log
tail -f /path/to/wordpress/wp-content/debug.log

# You should NOT see any WP Speakeasy errors
# If API is not configured, you'll see (this is normal):
# WP Speakeasy: API endpoint or token not configured. Reporting disabled.
```

---

## Troubleshooting

### Plugin Not Appearing in Admin

**Possible causes:**
- Files uploaded to wrong directory
- File permissions incorrect
- PHP syntax error

**Solutions:**
```bash
# Check plugin directory exists
ls -la /path/to/wordpress/wp-content/plugins/wp-speakeasy/

# Check main plugin file exists
ls -la /path/to/wordpress/wp-content/plugins/wp-speakeasy/wp-speakeasy.php

# Check file permissions (should be readable)
chmod 755 /path/to/wordpress/wp-content/plugins/wp-speakeasy
chmod 644 /path/to/wordpress/wp-content/plugins/wp-speakeasy/wp-speakeasy.php

# Check PHP error log for syntax errors
tail -f /var/log/php-error.log
```

### Application Passwords Section Not Showing

**Requirements:**
- WordPress 5.6+
- HTTPS enabled
- User has `edit_posts` capability

**Check WordPress version:**
```bash
wp core version
# Should be 5.6 or higher
```

**Check HTTPS:**
```bash
wp option get siteurl
# Should start with https://
```

**Force enable (should not be needed):**
```bash
# Verify plugin is forcing it
wp eval "var_dump(has_filter('wp_is_application_passwords_available'));"
```

### Composer Dependencies Missing

**Symptom:** Fatal error about missing `YahnisElsts\PluginUpdateChecker`

**Solution:**
```bash
cd /path/to/wordpress/wp-content/plugins/wp-speakeasy
composer install --no-dev
```

### Auto-Updates Not Working

**Check configuration:**
```bash
wp eval "echo defined('SPEAKEASY_GITHUB_REPO') ? SPEAKEASY_GITHUB_REPO : 'Not configured';"
```

**Check for updates manually:**
```bash
wp plugin update --all --dry-run
```

**Force update check (development):**
```bash
wp transient delete update_plugins
wp plugin list
```

### Permission Errors

**Symptom:** Cannot write to plugin directory

**Solution:**
```bash
# Fix ownership (replace www-data with your web server user)
sudo chown -R www-data:www-data /path/to/wordpress/wp-content/plugins/wp-speakeasy

# Fix permissions
sudo find /path/to/wordpress/wp-content/plugins/wp-speakeasy -type d -exec chmod 755 {} \;
sudo find /path/to/wordpress/wp-content/plugins/wp-speakeasy -type f -exec chmod 644 {} \;
```

### Module Not Loading

**Check module status:**
```bash
# Via WP-CLI
wp option get speakeasy_enabled_modules --format=json

# Should output: ["app-passwords","lap-meta"]
```

**Reset to defaults:**
```bash
wp option update speakeasy_enabled_modules '["app-passwords","lap-meta"]' --format=json
```

---

## Uninstallation

If you need to remove the plugin:

### Via WordPress Admin

1. Go to **Plugins** → **Installed Plugins**
2. **Deactivate** WP Speakeasy
3. Click **Delete**
4. Confirm deletion

### Via WP-CLI

```bash
wp plugin deactivate wp-speakeasy
wp plugin uninstall wp-speakeasy
```

### Manual Removal

```bash
# Deactivate first (optional)
wp plugin deactivate wp-speakeasy

# Remove plugin directory
rm -rf /path/to/wordpress/wp-content/plugins/wp-speakeasy

# Clean up options (optional)
wp option delete speakeasy_enabled_modules
wp db query "DELETE FROM wp_options WHERE option_name LIKE 'speakeasy_%'"

# Remove cron jobs
wp cron event delete speakeasy_daily_health_check
```

---

## Mass Deployment (Multiple Sites)

For deploying to multiple WordPress sites:

### Using WP-CLI Script

```bash
#!/bin/bash
# deploy-to-sites.sh

SITES=(
  "/var/www/site1.com"
  "/var/www/site2.com"
  "/var/www/site3.com"
)

PLUGIN_ZIP="/path/to/wp-speakeasy.zip"

for SITE_PATH in "${SITES[@]}"; do
  echo "Deploying to $SITE_PATH..."

  wp plugin install $PLUGIN_ZIP --activate --path=$SITE_PATH

  if [ $? -eq 0 ]; then
    echo "✅ Success: $SITE_PATH"
  else
    echo "❌ Failed: $SITE_PATH"
  fi
done
```

### Using Ansible

```yaml
# playbook.yml
---
- name: Deploy WP Speakeasy Plugin
  hosts: wordpress_servers
  tasks:
    - name: Upload plugin
      copy:
        src: /path/to/wp-speakeasy.zip
        dest: /tmp/wp-speakeasy.zip

    - name: Install plugin
      command: wp plugin install /tmp/wp-speakeasy.zip --activate
      args:
        chdir: /var/www/html

    - name: Clean up
      file:
        path: /tmp/wp-speakeasy.zip
        state: absent
```

Run with:
```bash
ansible-playbook -i inventory.ini playbook.yml
```

---

## Next Steps

After installation:

1. **Generate Application Password** for API access
   - Users → Profile → Application Passwords
   - Name it (e.g., "REST API Access")
   - Copy the generated password

2. **Test REST API Access**
   ```bash
   curl "https://yoursite.com/wp-json/wp/v2/pages?per_page=1&context=edit" \
     -u "admin:xxxx xxxx xxxx xxxx"
   ```

3. **Configure Auto-Updates** (optional)
   - Add GitHub configuration to wp-config.php
   - See [Configuration](#auto-update-configuration)

4. **Set Up Monitoring** (optional)
   - Build backend API endpoint
   - Add API configuration to wp-config.php
   - See [API_INTEGRATION.md](API_INTEGRATION.md)

5. **Add Custom LAP Schemas** (if needed)
   - Create schema files in `modules/lap-meta/schemas/`
   - See [README.md](../README.md#adding-custom-schemas)

---

## Support

For installation issues:

- **Documentation:** [README.md](../README.md)
- **GitHub Issues:** https://github.com/speakeasy/wp-speakeasy/issues
- **Email:** dev@speakeasy.com

---

## Changelog

### v1.0.0 (2026-06-20)
- Initial installation guide
- WordPress Admin, FTP, WP-CLI, and Git methods
- Configuration examples
- Troubleshooting section
- Mass deployment examples
