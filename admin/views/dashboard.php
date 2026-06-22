<?php
/**
 * Admin Dashboard View
 *
 * Template for the WP Speakeasy settings page.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="speakeasy-dashboard">
		<!-- Updates -->
		<div class="speakeasy-updates-card">
			<h2>Plugin Updates</h2>
			<?php if ( $update_info['github_configured'] ) : ?>
				<div id="update-status">
					<p>
						<strong>Current Version:</strong> <?php echo esc_html( $update_info['current_version'] ); ?><br>
						<strong>Latest Version:</strong>
						<span id="latest-version">
							<?php if ( $update_info['latest_version'] ) : ?>
								<?php echo esc_html( $update_info['latest_version'] ); ?>
							<?php else : ?>
								<em>Checking...</em>
							<?php endif; ?>
						</span>
					</p>

					<?php if ( $update_info['update_available'] ) : ?>
						<div class="notice notice-warning inline">
							<p><strong>Update Available!</strong> Version <?php echo esc_html( $update_info['latest_version'] ); ?> is now available.</p>
						</div>
						<p>
							<button type="button" id="trigger-update-btn" class="button button-primary">
								Update Now
							</button>
							<button type="button" id="check-update-btn" class="button">
								Check Again
							</button>
						</p>
					<?php else : ?>
						<div class="notice notice-success inline" id="update-success-notice" style="<?php echo $update_info['latest_version'] ? '' : 'display:none;'; ?>">
							<p>You are running the latest version!</p>
						</div>
						<p>
							<button type="button" id="check-update-btn" class="button">
								Check for Updates
							</button>
						</p>
					<?php endif; ?>

					<div id="update-message" style="margin-top: 10px;"></div>
				</div>
			<?php else : ?>
				<p>
					Auto-updates are not configured. To enable automatic updates, add this to your <code>wp-config.php</code>:
				</p>
				<pre>define( 'SPEAKEASY_GITHUB_REPO', 'your-org/wp-speakeasy' );</pre>
				<p>
					You can still update manually by downloading the latest version and installing it via WordPress Admin → Plugins → Add New → Upload Plugin.
				</p>
			<?php endif; ?>
		</div>

		<!-- Backend Registration -->
		<div class="speakeasy-registration-card">
			<h2>Backend Registration</h2>
			<?php if ( $registration_info['api_endpoint'] ) : ?>
				<p>
					<strong>API Endpoint:</strong> <?php echo esc_html( $registration_info['api_endpoint'] ); ?><br>
					<strong>Plugin API Key:</strong>
					<code id="api-key-display" style="user-select: all;"><?php echo esc_html( substr( $registration_info['api_key'], 0, 16 ) . '...' ); ?></code>
					<button type="button" id="toggle-api-key-btn" class="button button-small" data-full-key="<?php echo esc_attr( $registration_info['api_key'] ); ?>" data-masked-key="<?php echo esc_attr( substr( $registration_info['api_key'], 0, 16 ) . '...' ); ?>" style="margin-left: 5px;">
						Show Full Key
					</button>
					<br>
					<strong>Registration Status:</strong>
					<?php if ( $registration_info['registered'] ) : ?>
						<span style="color: green;">✓ Registered</span>
					<?php else : ?>
						<span style="color: orange;">⚠ Pending</span>
					<?php endif; ?>
				</p>

				<?php if ( $registration_info['registered'] ) : ?>
					<div class="notice notice-success inline">
						<p>This site is successfully registered with Speakeasy backend.</p>
					</div>
				<?php else : ?>
					<div class="notice notice-warning inline">
						<p>Registration pending. The plugin will automatically retry every hour until successful.</p>
					</div>
				<?php endif; ?>

				<p>
					<button type="button" id="send-activation-btn" class="button">
						<?php echo $registration_info['registered'] ? 'Re-send Registration' : 'Send Registration Now'; ?>
					</button>
				</p>

				<div id="registration-message" style="margin-top: 10px;"></div>
			<?php else : ?>
				<p>Backend registration is not configured.</p>
			<?php endif; ?>
		</div>

		<!-- Error Log -->
		<?php if ( $error_info['available'] ) : ?>
			<div class="speakeasy-errors-card">
				<h2>
					Error Log
					<?php if ( $error_info['count'] > 0 ) : ?>
						<span class="error-badge" style="background: #dc3232; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 10px;">
							<?php echo esc_html( $error_info['count'] ); ?>
						</span>
					<?php endif; ?>
				</h2>

				<?php if ( $error_info['count'] > 0 ) : ?>
					<p>
						<button type="button" id="clear-errors-btn" class="button">
							Clear Error Log
						</button>
						<button type="button" id="refresh-errors-btn" class="button" style="margin-left: 5px;">
							Refresh
						</button>
					</p>

					<div id="error-log-container">
						<table class="widefat" id="error-log-table">
							<thead>
								<tr>
									<th style="width: 80px;">Severity</th>
									<th style="width: 140px;">Timestamp</th>
									<th>Message</th>
									<th style="width: 200px;">Location</th>
									<th style="width: 60px;">Details</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $error_info['errors'] as $index => $error ) : ?>
									<tr>
										<td>
											<span class="error-type error-type-<?php echo esc_attr( $error['type'] ); ?>">
												<?php echo esc_html( ucfirst( $error['type'] ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $error['timestamp'] ); ?></td>
										<td><?php echo esc_html( $error['message'] ); ?></td>
										<td>
											<code style="font-size: 11px;">
												<?php echo esc_html( $error['file'] ); ?>
												<?php if ( $error['line'] > 0 ) : ?>
													:<?php echo esc_html( $error['line'] ); ?>
												<?php endif; ?>
											</code>
										</td>
										<td>
											<?php if ( ! empty( $error['trace'] ) || ! empty( $error['context'] ) ) : ?>
												<button type="button" class="button button-small toggle-details" data-index="<?php echo esc_attr( $index ); ?>">
													Show
												</button>
											<?php else : ?>
												<span style="color: #999;">—</span>
											<?php endif; ?>
										</td>
									</tr>
									<?php if ( ! empty( $error['trace'] ) || ! empty( $error['context'] ) ) : ?>
										<tr class="error-details" id="error-details-<?php echo esc_attr( $index ); ?>" style="display: none;">
											<td colspan="5" style="background: #f5f5f5; padding: 15px;">
												<?php if ( ! empty( $error['context'] ) ) : ?>
													<strong>Context:</strong>
													<pre style="margin: 5px 0; padding: 10px; background: white; overflow-x: auto;"><?php echo esc_html( print_r( $error['context'], true ) ); ?></pre>
												<?php endif; ?>
												<?php if ( ! empty( $error['trace'] ) ) : ?>
													<strong>Stack Trace:</strong>
													<pre style="margin: 5px 0; padding: 10px; background: white; overflow-x: auto; font-size: 11px;"><?php echo esc_html( $error['trace'] ); ?></pre>
												<?php endif; ?>
											</td>
										</tr>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div id="error-message" style="margin-top: 10px;"></div>
				<?php else : ?>
					<div class="notice notice-success inline" id="no-errors-notice">
						<p>No errors logged. Your plugin is running smoothly!</p>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<!-- Status Overview -->
		<div class="speakeasy-status-card">
			<h2>System Information</h2>
			<p>
				<strong>Plugin Version:</strong> <?php echo esc_html( $system_info['plugin_version'] ); ?><br>
				<strong>WordPress:</strong> <?php echo esc_html( $system_info['wordpress_version'] ); ?><br>
				<strong>PHP:</strong> <?php echo esc_html( $system_info['php_version'] ); ?>
			</p>
		</div>

		<!-- Modules -->
		<div class="speakeasy-modules-card">
			<h2>Modules</h2>
			<table class="widefat">
				<thead>
					<tr>
						<th>Module</th>
						<th>Version</th>
						<th>Status</th>
						<th>Priority</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $modules as $id => $module ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $module->get_name() ); ?></strong><br>
								<span style="color: #666;"><?php echo esc_html( $module->get_description() ); ?></span>
							</td>
							<td><?php echo esc_html( $module->get_version() ); ?></td>
							<td>
								<?php if ( $module->is_enabled() ) : ?>
									<span style="color: green;">Active</span>
								<?php else : ?>
									<span style="color: gray;">Inactive</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $module->get_priority() ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- Diagnostics -->
		<div class="speakeasy-diagnostics-card">
			<h2>Diagnostics</h2>
			<table class="widefat">
				<tbody>
					<tr>
						<td><strong>Application Passwords</strong></td>
						<td>
							<?php if ( $diagnostics['app_passwords'] ) : ?>
								<span style="color: green;">Available</span>
							<?php else : ?>
								<span style="color: red;">Not Available</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong>REST API</strong></td>
						<td>
							<?php if ( $diagnostics['rest_api'] ) : ?>
								<span style="color: green;">Accessible</span>
							<?php else : ?>
								<span style="color: red;">Not Accessible</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong>Meta Fields Registered</strong></td>
						<td><?php echo esc_html( $diagnostics['meta_fields'] ); ?> fields</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Quick Links -->
		<div class="speakeasy-links-card">
			<h2>Quick Links</h2>
			<p>
				<a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>" class="button">
					Manage Application Passwords
				</a>
				<a href="<?php echo esc_url( rest_url() ); ?>" class="button" target="_blank">
					View REST API
				</a>
			</p>
		</div>
	</div>

	<style>
		.speakeasy-dashboard > div {
			background: #fff;
			border: 1px solid #ccd0d4;
			padding: 20px;
			margin: 20px 0;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.speakeasy-dashboard h2 {
			margin-top: 0;
			padding-bottom: 10px;
			border-bottom: 1px solid #eee;
		}
		#update-message {
			padding: 10px;
			border-radius: 3px;
		}
		#update-message.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		#update-message.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		#update-message.info {
			background: #d1ecf1;
			color: #0c5460;
			border: 1px solid #bee5eb;
		}
		#registration-message {
			padding: 10px;
			border-radius: 3px;
		}
		#registration-message.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		#registration-message.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		#registration-message.info {
			background: #d1ecf1;
			color: #0c5460;
			border: #bee5eb;
		}
		#error-message {
			padding: 10px;
			border-radius: 3px;
		}
		#error-message.success {
			background: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		#error-message.error {
			background: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		.error-type {
			padding: 3px 8px;
			border-radius: 3px;
			font-size: 11px;
			font-weight: bold;
			text-transform: uppercase;
		}
		.error-type-error {
			background: #dc3232;
			color: white;
		}
		.error-type-warning {
			background: #ffb900;
			color: white;
		}
		.error-type-notice {
			background: #0073aa;
			color: white;
		}
		.error-type-exception {
			background: #d63638;
			color: white;
		}
		#error-log-table tbody tr:hover {
			background: #f9f9f9;
		}
	</style>

	<script>
	jQuery(document).ready(function($) {
		var updateNonce = '<?php echo esc_js( wp_create_nonce( 'speakeasy_update' ) ); ?>';
		var activationNonce = '<?php echo esc_js( wp_create_nonce( 'speakeasy_activation' ) ); ?>';
		var errorsNonce = '<?php echo esc_js( wp_create_nonce( 'speakeasy_errors' ) ); ?>';

		// Check for updates button
		$('#check-update-btn').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Checking...');
			$('#update-message').removeClass('success error info').hide();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'speakeasy_check_update',
					nonce: updateNonce
				},
				success: function(response) {
					if (response.success && response.data) {
						var info = response.data;
						$('#latest-version').text(info.latest_version || 'Unknown');

						if (info.update_available) {
							$('#update-success-notice').hide();
							$('#trigger-update-btn').show();
							$('#update-message')
								.addClass('info')
								.html('<strong>Update Available!</strong> Version ' + info.latest_version + ' is ready to install.')
								.show();
						} else {
							$('#trigger-update-btn').hide();
							$('#update-success-notice').show();
							$('#update-message')
								.addClass('success')
								.text('You are running the latest version!')
								.show();
						}
					}
					$btn.prop('disabled', false).text('Check Again');
				},
				error: function() {
					$('#update-message')
						.addClass('error')
						.text('Failed to check for updates. Please try again.')
						.show();
					$btn.prop('disabled', false).text('Check Again');
				}
			});
		});

		// Trigger update button
		$('#trigger-update-btn').on('click', function() {
			if (!confirm('This will update the plugin to the latest version. Continue?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Updating...');
			$('#check-update-btn').prop('disabled', true);
			$('#update-message')
				.removeClass('success error')
				.addClass('info')
				.html('<strong>Updating plugin...</strong> This may take a minute.')
				.show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'speakeasy_trigger_update',
					nonce: updateNonce
				},
				success: function(response) {
					// Debug: Log full response to console
					console.log('Update Response:', response);

					if (response.success) {
						$('#update-message')
							.removeClass('info error')
							.addClass('success')
							.html('<strong>Success!</strong> ' + response.data.message + ' <em>Reloading page...</em>')
							.show();

						// Reload page after 2 seconds
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						// Show detailed error with error code if available
						var errorMsg = response.data.message || 'Unknown error';
						if (response.data.error_code) {
							errorMsg += ' (Code: ' + response.data.error_code + ')';
						}

						$('#update-message')
							.removeClass('info success')
							.addClass('error')
							.html('<strong>Update Failed:</strong> ' + errorMsg)
							.show();
						$btn.prop('disabled', false).text('Update Now');
						$('#check-update-btn').prop('disabled', false);

						// Debug: Log to console for troubleshooting
						console.error('Update failed:', response.data);
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {
					// Debug: Log AJAX error details
					console.error('AJAX Error:', {
						status: jqXHR.status,
						statusText: textStatus,
						error: errorThrown,
						response: jqXHR.responseText
					});

					$('#update-message')
						.removeClass('info success')
						.addClass('error')
						.text('Update request failed. Check browser console (F12) for details.')
						.show();
					$btn.prop('disabled', false).text('Update Now');
					$('#check-update-btn').prop('disabled', false);
				}
			});
		});

		// Send activation button
		$('#send-activation-btn').on('click', function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Sending...');
			$('#registration-message').removeClass('success error info').hide();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'speakeasy_send_activation',
					nonce: activationNonce
				},
				success: function(response) {
					if (response.success) {
						$('#registration-message')
							.addClass('success')
							.html('<strong>Success!</strong> ' + response.data.message)
							.show();

						// Update status text
						$('.speakeasy-registration-card').find('strong:contains("Registration Status:")').next()
							.html('<span style="color: green;">✓ Registered</span>');

						// Update notice
						$('.speakeasy-registration-card .notice').removeClass('notice-warning').addClass('notice-success')
							.find('p').text('This site is successfully registered with Speakeasy backend.');

						$btn.text('Re-send Registration');
					} else {
						$('#registration-message')
							.addClass('error')
							.html('<strong>Error:</strong> ' + (response.data.message || 'Unknown error'))
							.show();
					}
					$btn.prop('disabled', false);
				},
				error: function() {
					$('#registration-message')
						.addClass('error')
						.text('Request failed. Please try again.')
						.show();
					$btn.prop('disabled', false).text($btn.data('original-text') || 'Send Registration Now');
				}
			});
		});

		// Toggle API key visibility
		$('#toggle-api-key-btn').on('click', function() {
			var $btn = $(this);
			var $display = $('#api-key-display');
			var isShowing = $btn.data('showing') === true;

			if (isShowing) {
				// Hide the full key
				$display.text($btn.data('masked-key'));
				$btn.text('Show Full Key');
				$btn.data('showing', false);
			} else {
				// Show the full key
				$display.text($btn.data('full-key'));
				$btn.text('Hide Key');
				$btn.data('showing', true);
			}
		});

		// Toggle error details
		$(document).on('click', '.toggle-details', function() {
			var $btn = $(this);
			var index = $btn.data('index');
			var $details = $('#error-details-' + index);

			if ($details.is(':visible')) {
				$details.hide();
				$btn.text('Show');
			} else {
				$details.show();
				$btn.text('Hide');
			}
		});

		// Clear errors button
		$('#clear-errors-btn').on('click', function() {
			if (!confirm('Are you sure you want to clear all errors? This cannot be undone.')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Clearing...');
			$('#error-message').removeClass('success error').hide();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'speakeasy_clear_errors',
					nonce: errorsNonce
				},
				success: function(response) {
					if (response.success) {
						$('#error-message')
							.addClass('success')
							.html('<strong>Success!</strong> ' + response.data.message)
							.show();

						// Hide error table and show success notice
						$('#error-log-container').hide();
						$('#clear-errors-btn').hide();
						$('#refresh-errors-btn').hide();
						$('#no-errors-notice').show();
						$('.error-badge').remove();
					} else {
						$('#error-message')
							.addClass('error')
							.html('<strong>Error:</strong> ' + (response.data.message || 'Unknown error'))
							.show();
					}
					$btn.prop('disabled', false).text('Clear Error Log');
				},
				error: function() {
					$('#error-message')
						.addClass('error')
						.text('Request failed. Please try again.')
						.show();
					$btn.prop('disabled', false).text('Clear Error Log');
				}
			});
		});

		// Refresh errors button
		$('#refresh-errors-btn').on('click', function() {
			location.reload();
		});
	});
	</script>
</div>
