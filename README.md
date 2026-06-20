# WP Speakeasy

WordPress automation plugin for REST API enhancements and Application Passwords management.

## Features

- **Application Passwords Enabler**: Force-enables WordPress Application Passwords, overriding theme and security plugin restrictions
- **LAP Meta Fields**: Exposes Local Area Page custom meta fields to the WordPress REST API
- **Auto-Updates**: Automatic updates via GitHub releases using Plugin Update Checker
- **API Reporting**: Reports activation, updates, and health status to external API
- **Admin Interface**: Settings page for module management and diagnostics

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- Composer (for development)

## Installation

### Manual Installation

1. Download the plugin ZIP file
2. Upload to WordPress via Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Configure settings at Settings → WP Speakeasy

### Development Installation

```bash
# Clone the repository
git clone https://github.com/speakeasy/wp-speakeasy.git
cd wp-speakeasy

# Install dependencies
composer install

# Symlink to WordPress plugins directory
ln -s $(pwd) /path/to/wordpress/wp-content/plugins/wp-speakeasy

# Activate in WordPress
wp plugin activate wp-speakeasy
```

## Configuration

Add these constants to your `wp-config.php`:

```php
// GitHub Auto-Updater (optional)
define( 'SPEAKEASY_GITHUB_REPO', 'speakeasy/wp-speakeasy' );
define( 'SPEAKEASY_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxx' ); // For private repos

// API Reporter (optional)
define( 'SPEAKEASY_API_ENDPOINT', 'https://api.speakeasy.com/wp-plugin' );
define( 'SPEAKEASY_API_TOKEN', 'spk_xxxxxxxxxxxx' );
```

## Modules

### Application Passwords Enabler

Forces WordPress Application Passwords to be available, even if disabled by:
- Security plugins (Wordfence, etc.)
- Theme functions
- Other plugin filters

**Priority**: 999 (runs last to override other filters)

### LAP Meta Fields

Exposes custom meta fields from Local Area Page templates to the REST API.

**Schema Files**: Located in `modules/lap-meta/schemas/`

Default schema: `localareapage.php`

#### Adding Custom Schemas

Create a new schema file for your LAP template:

```php
// modules/lap-meta/schemas/your-template.php
<?php
return array(
    'your_custom_field' => array(
        'type'          => 'string',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
    ),
);
```

## Development

### Running Tests

```bash
# Run PHPUnit tests
composer test

# Run with coverage
composer test:coverage

# Check coding standards
composer phpcs

# Auto-fix coding standards
composer phpcbf

# Run static analysis
composer phpstan
```

### Project Structure

```
wp-speakeasy/
├── wp-speakeasy.php          # Main plugin file
├── includes/                  # Core classes
│   ├── interface-module.php
│   ├── class-module-manager.php
│   ├── class-auto-updater.php
│   └── class-api-reporter.php
├── modules/                   # Feature modules
│   ├── app-passwords/
│   └── lap-meta/
├── admin/                     # Admin interface
│   ├── class-admin-page.php
│   └── views/
├── tests/                     # PHPUnit tests
├── composer.json
└── README.md
```

## REST API Usage

### Accessing LAP Meta Fields

```bash
# Get page with meta fields (requires Application Password)
curl -X GET "https://yoursite.com/wp-json/wp/v2/pages/123?context=edit" \
  -u "username:xxxx xxxx xxxx xxxx"
```

### Generating Application Password

1. Navigate to Users → Profile
2. Scroll to "Application Passwords" section
3. Enter name and click "Add New Application Password"
4. Copy the generated password

## API Endpoints

The plugin reports to these endpoints (if configured):

- `POST /activation` - Plugin activation
- `POST /update` - Plugin updates
- `POST /health` - Daily health checks
- `POST /error` - Error reporting

All API calls are non-blocking and fail silently.

## Security

- All admin functions require `manage_options` capability
- REST API meta field access requires `edit_posts` capability
- Nonce verification on all form submissions
- Input sanitization and output escaping throughout
- No sensitive data logged or transmitted

## Troubleshooting

### Application Passwords Not Appearing

1. Check WordPress version (5.6+)
2. Ensure HTTPS is enabled
3. Visit Settings → WP Speakeasy to verify module status
4. Check for conflicting security plugins

### Meta Fields Not in REST API

1. Verify LAP template is detected (check admin dashboard)
2. Ensure schema file exists for your template
3. Check REST API endpoint: `/wp-json/wp/v2/pages/{id}?context=edit`

### Auto-Updates Not Working

1. Verify `SPEAKEASY_GITHUB_REPO` is defined in wp-config.php
2. Check GitHub repository is accessible
3. For private repos, ensure `SPEAKEASY_GITHUB_TOKEN` is set

## Support

For issues, questions, or contributions:

- GitHub Issues: https://github.com/speakeasy/wp-speakeasy/issues
- Email: dev@speakeasy.com

## License

GPL-2.0+ - See LICENSE file for details

## Credits

Built by Speakeasy using WordPress best practices and following WordPress Coding Standards.

### Dependencies

- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by Yahnis Elsts
