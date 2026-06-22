<?php
/**
 * Simple Updater for WP Speakeasy
 *
 * Uses WP-CLI when available, falls back to direct download.
 * No external dependencies required.
 *
 * @package WP_Speakeasy
 * @since 1.2.0
 */

/**
 * Class Speakeasy_Simple_Updater
 *
 * Handles plugin updates using WP-CLI or direct download method.
 *
 * @since 1.2.0
 */
class Speakeasy_Simple_Updater {

	/**
	 * Get latest version info from GitHub
	 *
	 * @since 1.2.0
	 * @return array|WP_Error Version info or error.
	 */
	public function check_for_updates() {
		if ( ! defined( 'SPEAKEASY_GITHUB_REPO' ) ) {
			return new WP_Error( 'no_repo', 'GitHub repository not configured' );
		}

		// Check cache first (12 hours).
		$cached = get_transient( 'speakeasy_latest_version_info' );
		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch from GitHub API.
		$api_url  = 'https://api.github.com/repos/' . SPEAKEASY_GITHUB_REPO . '/releases/latest';
		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/vnd.github.v3+json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'api_error', 'GitHub API returned status ' . $code );
		}

		$body    = wp_remote_retrieve_body( $response );
		$release = json_decode( $body, true );

		if ( ! isset( $release['tag_name'] ) ) {
			return new WP_Error( 'invalid_response', 'No tag_name in GitHub release' );
		}

		// Find the wp-speakeasy.zip release asset (production build).
		// Never use zipball - it contains source code without Composer dependencies.
		$download_url = null;

		if ( isset( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && 'wp-speakeasy.zip' === $asset['name'] ) {
					$download_url = $asset['browser_download_url'];
					break;
				}
			}
		}

		if ( ! $download_url ) {
			return new WP_Error(
				'no_release_asset',
				'No wp-speakeasy.zip asset found in GitHub release. The release may not have completed building yet.'
			);
		}

		$version_info = array(
			'version'      => ltrim( $release['tag_name'], 'v' ),
			'download_url' => $download_url,
			'changelog'    => isset( $release['body'] ) ? $release['body'] : '',
			'published_at' => isset( $release['published_at'] ) ? $release['published_at'] : '',
		);

		// Log version check result.
		error_log(
			sprintf(
				'WP Speakeasy: Version check - Current: %s, Latest: %s, Download: %s',
				SPEAKEASY_VERSION,
				$version_info['version'],
				$version_info['download_url']
			)
		);

		// Cache for 12 hours.
		set_transient( 'speakeasy_latest_version_info', $version_info, 12 * HOUR_IN_SECONDS );

		return $version_info;
	}

	/**
	 * Check if update is available
	 *
	 * @since 1.2.0
	 * @return bool True if update available.
	 */
	public function is_update_available() {
		$info = $this->check_for_updates();

		if ( is_wp_error( $info ) ) {
			return false;
		}

		return version_compare( $info['version'], SPEAKEASY_VERSION, '>' );
	}

	/**
	 * Perform plugin update
	 *
	 * Tries WP-CLI first, falls back to direct download.
	 *
	 * @since 1.2.0
	 * @param string|null $version Specific version to install (default: latest).
	 * @return array|WP_Error Update result.
	 */
	public function update( $version = null ) {
		// Get update info.
		$info = $this->check_for_updates();

		if ( is_wp_error( $info ) ) {
			return $info;
		}

		// Check if update needed.
		if ( ! $version && ! $this->is_update_available() ) {
			return array(
				'success' => true,
				'message' => 'Already running the latest version',
				'version' => SPEAKEASY_VERSION,
			);
		}

		$download_url = $info['download_url'];
		$new_version  = $info['version'];

		// Log update attempt with source information.
		error_log(
			sprintf(
				'WP Speakeasy: Starting update - Current: %s, Target: %s, Download URL: %s',
				SPEAKEASY_VERSION,
				$new_version,
				$download_url
			)
		);

		// Try WP-CLI first (most reliable).
		$result = $this->update_via_wpcli( $download_url, $new_version );

		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		// Fallback: Direct download method.
		return $this->update_via_direct_download( $download_url, $new_version );
	}

	/**
	 * Update via WP-CLI
	 *
	 * @since 1.2.0
	 * @param string $download_url URL to plugin ZIP.
	 * @param string $new_version  New version number.
	 * @return array|WP_Error Update result.
	 */
	private function update_via_wpcli( $download_url, $new_version ) {
		// Check if exec() is available.
		if ( ! function_exists( 'exec' ) ) {
			return new WP_Error( 'exec_disabled', 'exec() function is disabled' );
		}

		// Check if WP-CLI is available.
		exec( 'which wp 2>&1', $output, $return_code );
		if ( 0 !== $return_code ) {
			return new WP_Error( 'wpcli_not_found', 'WP-CLI not found on server' );
		}

		// Build WP-CLI command.
		$command = sprintf(
			'wp plugin install %s --activate --force --path=%s 2>&1',
			escapeshellarg( $download_url ),
			escapeshellarg( ABSPATH )
		);

		// Execute command.
		exec( $command, $output, $return_code );

		if ( 0 === $return_code ) {
			$this->log_success( 'Updated via WP-CLI to version ' . $new_version . ' from ' . $download_url );

			// Clear cache.
			delete_transient( 'speakeasy_latest_version_info' );

			return array(
				'success'      => true,
				'message'      => 'Plugin updated successfully via WP-CLI to version ' . $new_version,
				'version'      => $new_version,
				'method'       => 'wpcli',
				'download_url' => $download_url,
			);
		}

		// WP-CLI failed.
		$error_msg = 'WP-CLI update failed: ' . implode( "\n", $output );
		$this->log_error( $error_msg );

		return new WP_Error( 'wpcli_failed', $error_msg );
	}

	/**
	 * Update via direct download
	 *
	 * Downloads ZIP, extracts, and replaces plugin files.
	 *
	 * @since 1.2.0
	 * @param string $download_url URL to plugin ZIP.
	 * @param string $new_version  New version number.
	 * @return array|WP_Error Update result.
	 */
	private function update_via_direct_download( $download_url, $new_version ) {
		// Require WordPress filesystem functions.
		require_once ABSPATH . 'wp-admin/includes/file.php';

		// Download file.
		$temp_file = download_url( $download_url, 300 );

		if ( is_wp_error( $temp_file ) ) {
			$this->log_error( 'Download failed: ' . $temp_file->get_error_message() );
			return $temp_file;
		}

		// Get WordPress filesystem.
		global $wp_filesystem;
		if ( ! WP_Filesystem() ) {
			$error = new WP_Error( 'filesystem_error', 'Could not initialize WordPress filesystem' );
			$this->log_error( $error->get_error_message() );
			return $error;
		}

		// Create temp extraction directory.
		$temp_dir = WP_CONTENT_DIR . '/upgrade/wp-speakeasy-' . time();
		if ( ! $wp_filesystem->mkdir( $temp_dir, 0755 ) ) {
			$error = new WP_Error( 'mkdir_failed', 'Could not create temp directory' );
			$this->log_error( $error->get_error_message() );
			return $error;
		}

		// Extract ZIP.
		$result = unzip_file( $temp_file, $temp_dir );
		unlink( $temp_file ); // Clean up downloaded ZIP.

		if ( is_wp_error( $result ) ) {
			$this->log_error( 'Extraction failed: ' . $result->get_error_message() );
			$wp_filesystem->rmdir( $temp_dir, true );
			return $result;
		}

		// Find the extracted folder (GitHub ZIPs have repo-name-hash format).
		$extracted_folders = glob( $temp_dir . '/*', GLOB_ONLYDIR );
		if ( empty( $extracted_folders ) ) {
			$error = new WP_Error( 'no_folder', 'No folder found in extracted ZIP' );
			$this->log_error( $error->get_error_message() );
			$wp_filesystem->rmdir( $temp_dir, true );
			return $error;
		}

		$source_dir = $extracted_folders[0];
		$plugin_dir = WP_PLUGIN_DIR . '/wp-speakeasy';

		// Backup old plugin.
		$backup_dir = WP_CONTENT_DIR . '/upgrade/wp-speakeasy-backup-' . time();
		if ( $wp_filesystem->exists( $plugin_dir ) ) {
			$wp_filesystem->move( $plugin_dir, $backup_dir );
		}

		// Move new plugin into place.
		$moved = $wp_filesystem->move( $source_dir, $plugin_dir );

		if ( ! $moved ) {
			// Restore backup.
			if ( $wp_filesystem->exists( $backup_dir ) ) {
				$wp_filesystem->move( $backup_dir, $plugin_dir );
			}

			$error = new WP_Error( 'move_failed', 'Could not move new plugin files into place' );
			$this->log_error( $error->get_error_message() );
			$wp_filesystem->rmdir( $temp_dir, true );
			return $error;
		}

		// Clean up.
		$wp_filesystem->rmdir( $temp_dir, true );
		$wp_filesystem->rmdir( $backup_dir, true );

		// Clear cache.
		delete_transient( 'speakeasy_latest_version_info' );

		$this->log_success( 'Updated via direct download to version ' . $new_version . ' from ' . $download_url );

		return array(
			'success'      => true,
			'message'      => 'Plugin updated successfully via direct download to version ' . $new_version,
			'version'      => $new_version,
			'method'       => 'direct',
			'download_url' => $download_url,
		);
	}

	/**
	 * Log success message
	 *
	 * @since 1.2.0
	 * @param string $message Success message.
	 * @return void
	 */
	private function log_success( $message ) {
		error_log( 'WP Speakeasy: ' . $message );

		if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
			Speakeasy_Error_Logger::instance()->log_error(
				'notice',
				$message,
				__FILE__,
				__LINE__,
				array( 'component' => 'Simple Updater' )
			);
		}
	}

	/**
	 * Log error message
	 *
	 * @since 1.2.0
	 * @param string $message Error message.
	 * @return void
	 */
	private function log_error( $message ) {
		error_log( 'WP Speakeasy: ' . $message );

		if ( class_exists( 'Speakeasy_Error_Logger' ) ) {
			Speakeasy_Error_Logger::instance()->log_error(
				'error',
				$message,
				__FILE__,
				__LINE__,
				array( 'component' => 'Simple Updater' )
			);
		}
	}
}
