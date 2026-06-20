<?php
/**
 * LAP Meta Field Schema for template: localareapage.php
 *
 * Defines custom meta fields for Local Area Pages that should be
 * exposed to the WordPress REST API.
 *
 * This schema is based on the common LAP template structure.
 * Additional schemas can be created for site-specific template variants.
 *
 * @package WP_Speakeasy
 * @since 1.0.0
 */

return array(
	'spk_main_heading'                => array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_video_section_left_text'     => array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_gridbox_repeater'            => array(
		'type'          => 'array',
		'single'        => true,
		'show_in_rest'  => array(
			'schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'spk_heading' => array( 'type' => 'string' ),
						'spk_image'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
						'spk_content' => array( 'type' => 'string' ),
					),
				),
			),
		),
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_cta_bg_color'                => array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_cta_bg_hvr_color'            => array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_call_to_action_box_text'     => array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),

	'spk_add_phone_number'            => array(
		'type'          => 'array',
		'single'        => true,
		'show_in_rest'  => array(
			'schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'spk_call_to_action_phone_number' => array( 'type' => 'string' ),
					),
				),
			),
		),
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	),
);
