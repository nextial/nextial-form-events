<?php
/**
 * Read-only MCP tools, so ChatGPT can look around after an event
 * ("a form arrived" -> was there an earlier one from the same person?).
 *
 * Only submissions already sent to ChatGPT as events, or kept while an
 * authorised AI client is connected, are available here (the last 50, at most
 * 30 days old). Nothing is read from the form plugins' own entry tables.
 *
 * @package NextialFormEvents
 */

defined( 'ABSPATH' ) || exit;

class Nextial_FE_Tools {

	public static function definitions() {
		$tools = array(
			array(
				'name'        => 'list_forms',
				'title'       => 'List forms',
				'description' => 'List the forms on this website and whether each one notifies ChatGPT.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => new stdClass(),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
			array(
				'name'        => 'list_recent_submissions',
				'title'       => 'List recent submissions',
				'description' => 'List recent form submissions (last 50, up to 30 days, kept while an AI client is connected), newest first. Optionally filter by form or by a text that appears in any field, such as an e-mail address. For "what is new since last time" (e.g. a scheduled task that runs every hour): pass since, then store checked_at from the result and pass it as since next time.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'since'   => array(
							'type'        => 'string',
							'description' => 'Only submissions made at or after this moment. ISO 8601 date-time (e.g. 2026-11-13T09:00:00Z) or Unix timestamp.',
						),
						'form_id' => array( 'type' => 'string' ),
						'search'  => array(
							'type'        => 'string',
							'description' => 'Case-insensitive text to look for in field values.',
						),
						'limit'   => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
						),
					),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
			array(
				'name'        => 'get_submission',
				'title'       => 'Get submission',
				'description' => 'Get one submission by its submission_id (from a form.submitted event or list_recent_submissions).',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'submission_id' => array( 'type' => 'string' ),
					),
					'required'             => array( 'submission_id' ),
					'additionalProperties' => false,
				),
				'annotations' => array( 'readOnlyHint' => true ),
			),
		);
		return apply_filters( 'nextial_fe_tool_definitions', $tools );
	}

	/**
	 * OAuth scope a tool needs: its "_scope" key (set by add-ons for write
	 * tools), otherwise the read scope.
	 */
	public static function scope_for( $name ) {
		foreach ( self::definitions() as $tool ) {
			if ( ( $tool['name'] ?? '' ) === $name && ! empty( $tool['_scope'] ) ) {
				return (string) $tool['_scope'];
			}
		}
		return Nextial_FE_OAuth::SCOPE_READ;
	}

	/** Definitions as sent to the client (internal keys removed). */
	public static function public_definitions() {
		$out = array();
		foreach ( self::definitions() as $tool ) {
			unset( $tool['_scope'] );
			$out[] = $tool;
		}
		return $out;
	}

	/**
	 * Run a tool. Returns structured data or WP_Error for tool-level errors.
	 *
	 * @return array|WP_Error
	 */
	public static function call( $name, array $args ) {
		/**
		 * Add-ons run their own tools here: return data or WP_Error, or null
		 * to let Nextial Form Events handle the call.
		 */
		$pre = apply_filters( 'nextial_fe_tool_call', null, $name, $args );
		if ( null !== $pre ) {
			return $pre;
		}
		switch ( $name ) {
			case 'list_forms':
				$forms = array();
				foreach ( Nextial_FE_Forms::all_forms() as $f ) {
					$forms[] = array(
						'form_id'         => $f['form_id'],
						'title'           => $f['title'],
						'plugin'          => $f['plugin'],
						'notifies_chatgpt' => $f['enabled'],
					);
				}
				return array(
					'plugins' => Nextial_FE_Forms::active_sources(),
					'forms'   => $forms,
				);

			case 'list_recent_submissions':
				$checked_at = time();
				$limit      = isset( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 10;
				$form       = isset( $args['form_id'] ) ? (string) $args['form_id'] : '';
				$search     = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
				$since      = null;
				if ( isset( $args['since'] ) && '' !== $args['since'] ) {
					$since = self::parse_moment( $args['since'] );
					if ( null === $since ) {
						return new WP_Error( 'nextial_fe_bad_date', 'since must be an ISO 8601 date-time (e.g. 2026-11-13T09:00:00Z) or a Unix timestamp.' );
					}
				}
				$out      = array();
				$has_more = false;
				foreach ( array_reverse( Nextial_FE_Events::recent() ) as $s ) {
					if ( null !== $since && strtotime( $s['submitted_at'] ) < $since ) {
						continue;
					}
					if ( '' !== $form && (string) $s['form_id'] !== $form ) {
						continue;
					}
					if ( '' !== $search ) {
						$hay = strtolower( implode( ' ', wp_list_pluck( (array) $s['fields'], 'value' ) ) );
						if ( false === strpos( $hay, $search ) ) {
							continue;
						}
					}
					if ( count( $out ) >= $limit ) {
						$has_more = true;
						break;
					}
					$out[] = $s;
				}
				return array(
					'submissions' => $out,
					'has_more'    => $has_more,
					'checked_at'  => gmdate( 'Y-m-d\\TH:i:s\\Z', $checked_at ),
				);

			case 'get_submission':
				$id = isset( $args['submission_id'] ) ? (string) $args['submission_id'] : '';
				foreach ( Nextial_FE_Events::recent() as $s ) {
					if ( $id === $s['submission_id'] ) {
						return $s;
					}
				}
				return new WP_Error( 'nextial_fe_not_found', 'Submission not found (only the last 50, up to 30 days old, are kept).' );
		}
		return new WP_Error( 'nextial_fe_unknown_tool', 'Unknown tool: ' . $name );
	}

	/**
	 * Parse an ISO 8601 date-time or a Unix timestamp into a Unix timestamp.
	 * A date-time without a time zone is read in UTC. Returns null if invalid.
	 *
	 * @param mixed $value Input.
	 * @return int|null
	 */
	public static function parse_moment( $value ) {
		if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^\\d{9,11}$/', $value ) ) ) {
			return (int) $value;
		}
		if ( ! is_string( $value ) || ! preg_match( '/^\\d{4}-\\d{2}-\\d{2}([T ]\\d{2}:\\d{2}(:\\d{2}(\\.\\d+)?)?)?(Z|[+-]\\d{2}:?\\d{2})?$/i', trim( $value ) ) ) {
			return null;
		}
		$ymd = array_map( 'intval', explode( '-', substr( trim( $value ), 0, 10 ) ) );
		if ( ! checkdate( $ymd[1], $ymd[2], $ymd[0] ) ) {
			return null;
		}
		try {
			$dt = new DateTimeImmutable( trim( $value ), new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return null;
		}
		return $dt->getTimestamp();
	}
}
