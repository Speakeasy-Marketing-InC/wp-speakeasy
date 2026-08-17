<?php
/**
 * LAP Meta Field Definitions — legacy_v1 variant
 *
 * Field set for sites running the legacy LAP plugin. The legacy template ships
 * under the same filename as the modern one (localareapage.php) but reads an
 * entirely different set of meta keys: squashed lowercase (spk_mainheading)
 * rather than underscore-separated (spk_main_heading). There is no overlap
 * between the two key sets.
 *
 * Each field declares the access path the legacy template uses to read it.
 * This is not cosmetic — see the 'read' key documentation below.
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Field definition keys:
 *
 * 'type' — value type, used for response shaping and validation.
 * 'enum' — when present, POST rejects values outside this list.
 * 'read' — the access path the legacy template uses:
 *
 *   'rwmb'      Meta Box's rwmb_meta() / rwmb_set_meta(), matching the
 *               template's rwmb_meta('spk_...') calls.
 *
 *   'post_meta' Raw get_post_meta( $id, $key, true ) / update_post_meta(),
 *               returning a bare attachment ID. The legacy template reads every
 *               image this way and feeds the result straight to
 *               wp_get_attachment_url(). Routing these through Meta Box instead
 *               risks storing a shape the template cannot read — a write that
 *               persists correctly but renders nothing, which is the exact
 *               silent-failure class session 6 was about.
 */
return array(

	// --- Header / video section ---------------------------------------
	'spk_mainheading'              => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_videolefttext'            => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_videocode'                => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_selectvideo'              => array(
		'type' => 'string',
		'read' => 'rwmb',
		// The legacy template branches on exactly these two values.
		'enum' => array( 'Youtube', 'Vimeo' ),
	),

	// --- Call to action -----------------------------------------------
	'spk_calltoactiontext'         => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	// Plain string in legacy. The modern variant stores this as
	// spk_add_phone_number, a repeater of objects — the shapes do not map.
	'spk_calltoactionnumber'       => array(
		'type' => 'string',
		'read' => 'rwmb',
	),

	// --- Bottom content blocks ----------------------------------------
	// Legacy has three fixed blocks. The modern variant replaced them with
	// spk_gridbox_repeater, a variable-length repeater with no counterpart here.
	'spk_bottomsectionheading'     => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_bottomsectionheading2'    => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_bottomsectionheading3'    => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_bottomsectioncontent'     => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_bottomsectioncontent2'    => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_bottomsectioncontent3'    => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	// Controls whether the second CTA band renders between blocks 2 and 3.
	'spk_bottomsectioncall2'       => array(
		'type' => 'boolean',
		'read' => 'rwmb',
	),

	// --- Map section ---------------------------------------------------
	'spk_mapsection'               => array(
		'type' => 'boolean',
		'read' => 'rwmb',
	),
	'spk_mapheading'               => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_mapaddress'               => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_mapphone'                 => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_mapfax'                   => array(
		'type' => 'string',
		'read' => 'rwmb',
	),
	'spk_mapiframe'                => array(
		'type' => 'string',
		'read' => 'rwmb',
	),

	// --- Images: bare attachment IDs, read via get_post_meta ------------
	'spk_bannerbgimg'              => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_videoimg'                 => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_calltoactionimg'          => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_mapimg'                   => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_bottomsectioncontentimg'  => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_bottomsectioncontentimg2' => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
	'spk_bottomsectioncontentimg3' => array(
		'type' => 'integer',
		'read' => 'post_meta',
	),
);
