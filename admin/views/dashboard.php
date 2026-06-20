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
		<!-- Status Overview -->
		<div class="speakeasy-status-card">
			<h2>Status</h2>
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
	</style>
</div>
