# WP Speakeasy

WordPress automation plugin for REST API enhancements and Application Passwords management.

## Features

- **Application Passwords Enabler**: Force-enables WordPress Application Passwords, overriding theme and security plugin restrictions
- **REST API for Application Passwords**: Programmatically create Application Passwords using plugin API key authentication
- **LAP Meta Fields**: Exposes Local Area Page custom meta fields to the WordPress REST API
- **Auto-Updates**: Automatic updates via GitHub releases using Plugin Update Checker
- **API Reporting**: Reports activation, updates, and health status to external API
- **Error Logging**: Comprehensive error tracking and dashboard display for debugging
- **Admin Interface**: Settings page for module management, diagnostics, and error monitoring

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

**No configuration required!** The plugin works out of the box with:
- Automatic backend reporting to Speakeasy's production server
- Automatic updates from GitHub releases
- Zero-configuration deployment

### How It Works

1. **Install & activate** the plugin on a WordPress site
2. **Plugin auto-generates** a unique 64-character API key for this site
3. **Registers with Speakeasy backend** automatically
4. **Sends daily health checks** to monitor plugin status
5. **Reports errors and updates** automatically
6. **Auto-updates from GitHub** when new releases are published

No manual configuration needed - everything works automatically!

### Optional Configuration

Only needed if you want to customize default behavior:

```php
// Custom API Endpoint (optional - defaults to Speakeasy production server)
define( 'SPEAKEASY_API_ENDPOINT', 'https://your-custom-endpoint.com/api/wordpress-sites' );

// Custom GitHub Repository (optional - defaults to Speakeasy-Marketing-InC/wp-speakeasy)
define( 'SPEAKEASY_GITHUB_REPO', 'your-org/your-fork' );

// GitHub Token (optional - only needed for private repositories)
define( 'SPEAKEASY_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxx' );
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
│   ├── class-simple-updater.php
│   ├── class-error-logger.php
│   ├── class-api-reporter.php
│   └── class-rest-api.php
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

### Creating Application Passwords Programmatically

The plugin provides a REST API endpoint for creating Application Passwords remotely using the plugin API key.

**Endpoint**: `POST /wp-json/speakeasy/v1/application-passwords`

**Authentication**: Plugin API key via `X-Speakeasy-API-Key` header

**Request**:
```bash
curl -X POST https://yoursite.com/wp-json/speakeasy/v1/application-passwords \
  -H "X-Speakeasy-API-Key: YOUR_PLUGIN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "name": "Speakeasy Backend Access"
  }'
```

**Response**:
```json
{
  "success": true,
  "password": "abcd 1234 efgh 5678 ijkl 9012",
  "username": "admin",
  "user_id": 1,
  "name": "Speakeasy Backend Access"
}
```

**Features**:
- Automatically revokes existing passwords with the same name
- Returns password only once (not stored or logged)
- Full error handling with proper HTTP status codes
- Audit logging for security monitoring

**Finding Your Plugin API Key**:
- Visit Settings → WP Speakeasy in WordPress admin
- The API key is displayed in the "Backend Registration" section
- Click "Show Full Key" to reveal the complete key

### Accessing LAP Meta Fields

```bash
# Get page with meta fields (requires Application Password)
curl -X GET "https://yoursite.com/wp-json/wp/v2/pages/123?context=edit" \
  -u "username:xxxx xxxx xxxx xxxx"
```

### Generating Application Password Manually

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

1. Auto-updates work automatically - no configuration needed
2. Check WP Speakeasy admin page for update status
3. Use manual "Check for Updates" button to force check
4. Verify GitHub repository is accessible (defaults to Speakeasy-Marketing-InC/wp-speakeasy)
5. For custom/private repos, ensure `SPEAKEASY_GITHUB_REPO` and `SPEAKEASY_GITHUB_TOKEN` are set

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
