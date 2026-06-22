<?php
/**
 * Debug script for testing update mechanism
 *
 * Place this file in wp-speakeasy plugin folder and access via:
 * https://your-site.com/wp-content/plugins/wp-speakeasy/debug-update.php
 *
 * @package WP_Speakeasy
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

header('Content-Type: text/plain');

echo "=== WP Speakeasy Update Debug ===\n\n";

// Check if Plugin Update Checker is loaded
echo "1. Plugin Update Checker Library:\n";
if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    echo "   ✓ FOUND\n\n";
} else {
    echo "   ✗ NOT FOUND - Run 'composer install'\n\n";
    exit;
}

// Check auto-updater instance
echo "2. Auto-Updater Instance:\n";
if (isset($GLOBALS['speakeasy_auto_updater'])) {
    echo "   ✓ FOUND\n\n";
    $updater = $GLOBALS['speakeasy_auto_updater'];
} else {
    echo "   ✗ NOT FOUND - Plugin not initialized properly\n\n";
    exit;
}

// Check update checker
echo "3. Update Checker Initialization:\n";
$update_checker = $updater->get_update_checker();
if ($update_checker) {
    echo "   ✓ INITIALIZED\n\n";
} else {
    echo "   ✗ NOT INITIALIZED\n\n";
    exit;
}

// Try to get update info from GitHub
echo "4. Fetching Update Info from GitHub:\n";
echo "   Repository: " . SPEAKEASY_GITHUB_REPO . "\n";
echo "   Current Version: " . SPEAKEASY_VERSION . "\n";

try {
    $update_info = $update_checker->requestInfo();

    if ($update_info) {
        echo "   ✓ SUCCESS\n";
        echo "   Latest Version: " . $update_info->version . "\n";
        echo "   Download URL: " . $update_info->download_url . "\n\n";

        // Check if update is available
        echo "5. Version Comparison:\n";
        $comparison = version_compare($update_info->version, SPEAKEASY_VERSION);
        echo "   version_compare('{$update_info->version}', '" . SPEAKEASY_VERSION . "') = {$comparison}\n";

        if ($comparison > 0) {
            echo "   ✓ UPDATE AVAILABLE\n\n";
        } elseif ($comparison === 0) {
            echo "   = SAME VERSION\n\n";
        } else {
            echo "   < OLDER VERSION\n\n";
        }

        // Show full update info
        echo "6. Full Update Info:\n";
        echo "   " . print_r($update_info, true) . "\n";

    } else {
        echo "   ✗ FAILED - No update info returned\n";
        echo "   This usually means:\n";
        echo "   - No GitHub release exists\n";
        echo "   - GitHub API rate limit exceeded\n";
        echo "   - Network/firewall blocking GitHub\n\n";
    }

} catch (Exception $e) {
    echo "   ✗ EXCEPTION: " . $e->getMessage() . "\n\n";
}

// Check WordPress permissions
echo "7. WordPress File Permissions:\n";
$plugin_path = WP_PLUGIN_DIR . '/wp-speakeasy';
if (is_writable($plugin_path)) {
    echo "   ✓ Plugin directory is writable\n";
} else {
    echo "   ✗ Plugin directory is NOT writable\n";
    echo "   Path: {$plugin_path}\n";
}

// Check if we can load upgrader classes
echo "\n8. WordPress Upgrader Classes:\n";
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

if (class_exists('Plugin_Upgrader')) {
    echo "   ✓ Plugin_Upgrader class available\n";
} else {
    echo "   ✗ Plugin_Upgrader class NOT available\n";
}

if (class_exists('WP_Ajax_Upgrader_Skin')) {
    echo "   ✓ WP_Ajax_Upgrader_Skin class available\n";
} else {
    echo "   ✗ WP_Ajax_Upgrader_Skin class NOT available\n";
}

echo "\n=== Debug Complete ===\n";
