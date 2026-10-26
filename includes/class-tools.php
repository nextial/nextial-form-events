<?php
/**
 * Read-only MCP tools, so ChatGPT can look around after an event
 * ("a form arrived" -> was there an earlier one from the same person?).
 *
 * Only submissions already sent to ChatGPT as events are available here
 * (the last 50, at most 30 days old). Nothing is read from the form plugins'
 * own entry tables.
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
				'description' => 'List recent form submissions already sent to ChatGPT (last 50, up to 30 days), newest first. Optionally filter by form or by a text that appears in any field, such as an e-mail address.',
				'inputSchema' => array(
					'type'                 => 'object',
					'properties'           => array(
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
	 * Run a tool. Returns structured data or WP_Error for tool-level errors.
	 *
	 * @return array|WP_Error
	 */
	public static function call( $name, array $args ) {
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
				$limit  = isset( $args['limit'] ) ? max( 1, min( 50, (int) $args['limit'] ) ) : 10;
				$form   = isset( $args['form_id'] ) ? (string) $args['form_id'] : '';
				$search = isset( $args['search'] ) ? strtolower( trim( (string) $args['search'] ) ) : '';
				$out    = array();
				foreach ( array_reverse( Nextial_FE_Events::recent() ) as $s ) {
					if ( '' !== $form && (string) $s['form_id'] !== $form ) {
						continue;
					}
					if ( '' !== $search ) {
						$hay = strtolower( implode( ' ', wp_list_pluck( (array) $s['fields'], 'value' ) ) );
						if ( false === strpos( $hay, $search ) ) {
							continue;
						}
					}
					$out[] = $s;
					if ( count( $out ) >= $limit ) {
						break;
					}
				}
				return array( 'submissions' => $out );

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
}
