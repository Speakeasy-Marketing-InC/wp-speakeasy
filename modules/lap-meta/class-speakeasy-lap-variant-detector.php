<?php
/**
 * LAP Plugin Variant Detector
 *
 * Determines which LAP plugin variant a page or site uses, by probing which
 * meta key style is actually present on the page.
 *
 * @package WP_Speakeasy
 * @since   1.6.0
 */

/**
 * Class Speakeasy_LAP_Variant_Detector
 *
 * The legacy and modern LAP plugins both ship a template named
 * localareapage.php, so the template filename carries no variant information.
 * The only reliable signal is which meta keys exist on the page: legacy uses
 * squashed lowercase keys (spk_mainheading), modern uses underscore-separated
 * ones (spk_main_heading). The two sets do not overlap.
 *
 * @since 1.6.0
 */
class Speakeasy_LAP_Variant_Detector {


	/**
	 * Template slug that identifies a Local Area Page
	 *
	 * @var string
	 */
	const LAP_TEMPLATE = 'localareapage.php';

	/**
	 * Legacy variant identifier
	 *
	 * @var string
	 */
	const VARIANT_LEGACY_V1 = 'legacy_v1';

	/**
	 * Modern variant identifier
	 *
	 * @var string
	 */
	const VARIANT_MODERN = 'modern';

	/**
	 * Verdict when a page carries both key styles
	 *
	 * @var string
	 */
	const VARIANT_AMBIGUOUS = 'ambiguous';

	/**
	 * Verdict when a page carries neither key style
	 *
	 * @var string
	 */
	const VARIANT_UNDETERMINED = 'undetermined';

	/**
	 * Marker keys per variant
	 *
	 * Chosen because they are present on any page of that variant that has been
	 * filled in at all, and because each is unique to its variant. Detection
	 * only needs a signal, not the full field set.
	 *
	 * @var array<string, string[]>
	 */
	const MARKERS = array(
		self::VARIANT_LEGACY_V1 => array(
			'spk_mainheading',
			'spk_calltoactiontext',
			'spk_videolefttext',
		),
		self::VARIANT_MODERN    => array(
			'spk_main_heading',
			'spk_call_to_action_box_text',
			'spk_video_section_left_text',
		),
	);

	/**
	 * Detect the variant of a single page
	 *
	 * @since  1.6.0
	 * @param  int $page_id Post ID to probe.
	 * @return string One of the VARIANT_* constants.
	 */
	public function detect_page( int $page_id ): string {
		$legacy = $this->count_present_markers( $page_id, self::VARIANT_LEGACY_V1 );
		$modern = $this->count_present_markers( $page_id, self::VARIANT_MODERN );

		return $this->verdict( $legacy > 0, $modern > 0 );
	}

	/**
	 * List the marker keys present on a page, grouped by variant
	 *
	 * Used to build an actionable ambiguity error naming the actual conflict
	 * rather than just reporting that one exists.
	 *
	 * @since  1.6.0
	 * @param  int $page_id Post ID to probe.
	 * @return array<string, string[]> Variant identifier => present marker keys.
	 */
	public function get_present_markers( int $page_id ): array {
		$present = array();

		foreach ( self::MARKERS as $variant => $keys ) {
			$found = array();

			foreach ( $keys as $key ) {
				$value = get_post_meta( $page_id, $key, true );
				if ( ! empty( $value ) ) {
					$found[] = $key;
				}
			}

			$present[ $variant ] = $found;
		}

		return $present;
	}

	/**
	 * Detect the dominant variant across every LAP page on the site
	 *
	 * Runs a fixed number of queries regardless of how many LAP pages exist —
	 * a per-page loop would scale badly on sites with hundreds of local area
	 * pages, which is the normal case for this plugin's audience.
	 *
	 * @since  1.6.0
	 * @return array{variant: string, mixed: bool, counts: array<string, int>, total_lap_pages: int}
	 */
	public function detect_site(): array {
		$total  = $this->count_lap_pages();
		$counts = array(
			self::VARIANT_LEGACY_V1    => 0,
			self::VARIANT_MODERN       => 0,
			self::VARIANT_AMBIGUOUS    => 0,
			self::VARIANT_UNDETERMINED => 0,
		);

		if ( 0 === $total ) {
			return array(
				'variant'         => self::VARIANT_UNDETERMINED,
				'mixed'           => false,
				'counts'          => $counts,
				'total_lap_pages' => 0,
			);
		}

		$markers_by_page = $this->fetch_markers_for_all_lap_pages();

		foreach ( $markers_by_page as $variants ) {
			$verdict = $this->verdict(
				in_array( self::VARIANT_LEGACY_V1, $variants, true ),
				in_array( self::VARIANT_MODERN, $variants, true )
			);
			++$counts[ $verdict ];
		}

		// Pages with no marker keys at all never appear in the marker query.
		$counts[ self::VARIANT_UNDETERMINED ] = $total - count( $markers_by_page );

		return array(
			'variant'         => $this->dominant_variant( $counts ),
			'mixed'           => $counts[ self::VARIANT_LEGACY_V1 ] > 0 && $counts[ self::VARIANT_MODERN ] > 0,
			'counts'          => $counts,
			'total_lap_pages' => $total,
		);
	}

	/**
	 * Resolve a verdict from which key styles are present
	 *
	 * @since  1.6.0
	 * @param  bool $has_legacy Legacy markers present.
	 * @param  bool $has_modern Modern markers present.
	 * @return string One of the VARIANT_* constants.
	 */
	private function verdict( bool $has_legacy, bool $has_modern ): string {
		if ( $has_legacy && $has_modern ) {
			return self::VARIANT_AMBIGUOUS;
		}

		if ( $has_legacy ) {
			return self::VARIANT_LEGACY_V1;
		}

		if ( $has_modern ) {
			return self::VARIANT_MODERN;
		}

		return self::VARIANT_UNDETERMINED;
	}

	/**
	 * Pick the dominant determinate variant from a tally
	 *
	 * Ambiguous and undetermined pages are never reported as the site variant —
	 * they are counts to act on, not an answer to "which plugin is this site
	 * running". Ties resolve to legacy, since a site with equal numbers of both
	 * has legacy pages that are actively rendering and need the legacy route.
	 *
	 * @since  1.6.0
	 * @param  array<string, int> $counts Per-verdict page counts.
	 * @return string One of the VARIANT_* constants.
	 */
	private function dominant_variant( array $counts ): string {
		$legacy = $counts[ self::VARIANT_LEGACY_V1 ];
		$modern = $counts[ self::VARIANT_MODERN ];

		if ( 0 === $legacy && 0 === $modern ) {
			return self::VARIANT_UNDETERMINED;
		}

		return $legacy >= $modern ? self::VARIANT_LEGACY_V1 : self::VARIANT_MODERN;
	}

	/**
	 * Count pages using the LAP template
	 *
	 * @since  1.6.0
	 * @return int
	 */
	private function count_lap_pages(): int {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta}
				WHERE meta_key = %s
				AND meta_value = %s",
				'_wp_page_template',
				self::LAP_TEMPLATE
			)
		);

		return (int) $count;
	}

	/**
	 * Fetch which variants' markers are present, for every LAP page at once
	 *
	 * One query for the whole site. Pages with no markers are absent from the
	 * result — the caller derives the undetermined count from the total.
	 *
	 * @since  1.6.0
	 * @return array<int, string[]> Post ID => variant identifiers with markers present.
	 */
	private function fetch_markers_for_all_lap_pages(): array {
		global $wpdb;

		$marker_keys  = array_merge( ...array_values( self::MARKERS ) );
		$placeholders = implode( ', ', array_fill( 0, count( $marker_keys ), '%s' ) );

		$params = array_merge(
			array( '_wp_page_template', self::LAP_TEMPLATE ),
			$marker_keys
		);

		// Placeholders are generated from a hardcoded key list, never from input.
		// The replacement count is dynamic for the same reason, so the sniff that
		// counts them statically cannot verify it; $wpdb->prepare() accepts the
		// arguments as a single array, which is what is passed here.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT marker.post_id, marker.meta_key
				FROM {$wpdb->postmeta} AS marker
				INNER JOIN {$wpdb->postmeta} AS tpl
					ON tpl.post_id = marker.post_id
					AND tpl.meta_key = %s
					AND tpl.meta_value = %s
				WHERE marker.meta_key IN ( {$placeholders} )
				AND marker.meta_value != ''",
				$params
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return array();
		}

		$by_page = array();

		foreach ( $rows as $row ) {
			$variant = $this->variant_for_marker_key( $row->meta_key );

			if ( null === $variant ) {
				continue;
			}

			$post_id = (int) $row->post_id;

			if ( ! isset( $by_page[ $post_id ] ) ) {
				$by_page[ $post_id ] = array();
			}

			if ( ! in_array( $variant, $by_page[ $post_id ], true ) ) {
				$by_page[ $post_id ][] = $variant;
			}
		}

		return $by_page;
	}

	/**
	 * Map a marker key back to the variant that owns it
	 *
	 * @since  1.6.0
	 * @param  string $meta_key Marker meta key.
	 * @return string|null Variant identifier, or null if not a marker key.
	 */
	private function variant_for_marker_key( string $meta_key ): ?string {
		foreach ( self::MARKERS as $variant => $keys ) {
			if ( in_array( $meta_key, $keys, true ) ) {
				return $variant;
			}
		}

		return null;
	}

	/**
	 * Count how many of a variant's marker keys are non-empty on a page
	 *
	 * @since  1.6.0
	 * @param  int    $page_id Post ID to probe.
	 * @param  string $variant Variant identifier.
	 * @return int
	 */
	private function count_present_markers( int $page_id, string $variant ): int {
		$found = 0;

		foreach ( self::MARKERS[ $variant ] as $key ) {
			$value = get_post_meta( $page_id, $key, true );

			if ( ! empty( $value ) ) {
				++$found;
			}
		}

		return $found;
	}
}
