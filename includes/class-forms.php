<?php
/**
 * Form plugin adapters: Contact Form 7, WPForms, Fluent Forms, Gravity Forms.
 *
 * Each adapter hooks the plugin's "valid submission" action, normalises the
 * entry to a common shape and hands it to Nextial_FE_Events::submitted().
 * Entries the form plugin flagged as spam never reach this class.
 *
 * @package NextialFormEvents
 */

defined( 'ABSPATH' ) || exit;

class Nextial_FE_Forms {

	const OPT_DISABLED = 'nextial_fe_disabled_forms';
	const OPT_EXCLUDE  = 'nextial_fe_excluded_fields';

	const MAX_FIELDS = 50;
	const MAX_VALUE  = 2000;

	/** Field types that are never sent, whatever the plugin calls them. */
	const SENSITIVE_TYPES = array( 'password', 'file', 'fileupload', 'file-upload', 'input_file', 'creditcard', 'credit-card', 'stripe-credit-card', 'authorize_net', 'square', 'paypal-commerce', 'payment_method', 'captcha', 'recaptcha', 'hcaptcha', 'turnstile', 'quiz', 'html', 'section', 'page', 'divider', 'pagebreak', 'signature', 'consent_hidden' );

	public static function init() {
		// Contact Form 7: fires only for valid, non-spam submissions.
		add_action( 'wpcf7_mail_sent', array( __CLASS__, 'on_cf7' ), 20, 1 );
		add_action( 'wpcf7_mail_failed', array( __CLASS__, 'on_cf7' ), 20, 1 );
		// WPForms (Lite and Pro).
		add_action( 'wpforms_process_complete', array( __CLASS__, 'on_wpforms' ), 20, 4 );
		// Fluent Forms (new and legacy hook names).
		add_action( 'fluentform/submission_inserted', array( __CLASS__, 'on_fluent' ), 20, 3 );
		add_action( 'fluentform_submission_inserted', array( __CLASS__, 'on_fluent_legacy' ), 20, 3 );
		// Gravity Forms.
		add_action( 'gform_after_submission', array( __CLASS__, 'on_gravity' ), 20, 2 );
	}

	/* ------------------------------------------------------------------ */
	/* Sources                                                             */
	/* ------------------------------------------------------------------ */

	public static function source_label( $plugin ) {
		$labels = array(
			'cf7'     => 'Contact Form 7',
			'wpforms' => 'WPForms',
			'fluent'  => 'Fluent Forms',
			'gravity' => 'Gravity Forms',
		);
		return isset( $labels[ $plugin ] ) ? $labels[ $plugin ] : (string) $plugin;
	}

	/**
	 * Active, supported form plugins.
	 *
	 * @return string[]
	 */
	public static function active_sources() {
		$out = array();
		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			$out[] = 'cf7';
		}
		if ( function_exists( 'wpforms' ) ) {
			$out[] = 'wpforms';
		}
		if ( defined( 'FLUENTFORM' ) || function_exists( 'wpFluentForm' ) ) {
			$out[] = 'fluent';
		}
		if ( class_exists( 'GFAPI' ) ) {
			$out[] = 'gravity';
		}
		return $out;
	}

	/**
	 * Every form of every active plugin.
	 *
	 * @return array<int,array{key:string,plugin:string,form_id:string,title:string,enabled:bool}>
	 */
	public static function all_forms() {
		global $wpdb;
		$forms = array();
		if ( class_exists( 'WPCF7_ContactForm' ) ) {
			foreach ( (array) WPCF7_ContactForm::find( array( 'posts_per_page' => 200 ) ) as $f ) {
				$forms[] = self::form_row( 'cf7', $f->id(), $f->title() );
			}
		}
		if ( function_exists( 'wpforms' ) ) {
			$handler = method_exists( wpforms(), 'obj' ) ? wpforms()->obj( 'form' ) : ( isset( wpforms()->form ) ? wpforms()->form : null );
			$list    = $handler ? $handler->get( '', array( 'posts_per_page' => 200 ) ) : array();
			foreach ( (array) $list as $f ) {
				if ( isset( $f->ID ) ) {
					$forms[] = self::form_row( 'wpforms', $f->ID, $f->post_title );
				}
			}
		}
		if ( in_array( 'fluent', self::active_sources(), true ) ) {
			$table = $wpdb->prefix . 'fluentform_forms';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$rows = $wpdb->get_results( "SELECT id, title FROM {$table} ORDER BY id ASC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				foreach ( (array) $rows as $r ) {
					$forms[] = self::form_row( 'fluent', $r['id'], $r['title'] );
				}
			}
		}
		if ( class_exists( 'GFAPI' ) ) {
			foreach ( (array) GFAPI::get_forms() as $f ) {
				$forms[] = self::form_row( 'gravity', $f['id'], $f['title'] );
			}
		}
		return $forms;
	}

	private static function form_row( $plugin, $id, $title ) {
		$key = $plugin . ':' . $id;
		return array(
			'key'     => $key,
			'plugin'  => $plugin,
			'form_id' => (string) $id,
			'title'   => (string) $title,
			'enabled' => self::is_enabled( $plugin, $id ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Settings                                                            */
	/* ------------------------------------------------------------------ */

	public static function is_enabled( $plugin, $form_id ) {
		$disabled = get_option( self::OPT_DISABLED, array() );
		return ! ( is_array( $disabled ) && in_array( $plugin . ':' . $form_id, $disabled, true ) );
	}

	public static function set_disabled( array $keys ) {
		update_option( self::OPT_DISABLED, array_values( array_unique( array_map( 'strval', $keys ) ) ), false );
	}

	/**
	 * @return string[] lower-case field names/labels never sent.
	 */
	public static function excluded_fields() {
		$v = get_option( self::OPT_EXCLUDE, array() );
		return is_array( $v ) ? $v : array();
	}

	public static function set_excluded_fields( $csv ) {
		$list = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $csv ) ) ) );
		update_option( self::OPT_EXCLUDE, array_values( array_unique( $list ) ), false );
	}

	/* ------------------------------------------------------------------ */
	/* Adapters                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * Contact Form 7.
	 *
	 * @param WPCF7_ContactForm $form Form.
	 */
	public static function on_cf7( $form ) {
		if ( ! is_object( $form ) || ! method_exists( $form, 'id' ) || ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}
		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}
		$types = array();
		if ( method_exists( $form, 'scan_form_tags' ) ) {
			foreach ( (array) $form->scan_form_tags() as $tag ) {
				if ( ! empty( $tag->name ) ) {
					$types[ $tag->name ] = (string) $tag->basetype;
				}
			}
		}
		$fields = array();
		foreach ( (array) $submission->get_posted_data() as $name => $value ) {
			$name = (string) $name;
			if ( '' === $name || '_' === $name[0] ) {
				continue;
			}
			$fields[] = array(
				'name'  => $name,
				'label' => $name,
				'type'  => isset( $types[ $name ] ) ? $types[ $name ] : '',
				'value' => $value,
			);
		}
		$url = method_exists( $submission, 'get_meta' ) ? (string) $submission->get_meta( 'url' ) : '';
		self::submit( 'cf7', $form->id(), $form->title(), null, $fields, $url, admin_url( 'admin.php?page=wpcf7&post=' . (int) $form->id() . '&action=edit' ) );
	}

	/**
	 * WPForms.
	 */
	public static function on_wpforms( $fields_in, $entry, $form_data, $entry_id ) {
		$form_id = isset( $form_data['id'] ) ? (int) $form_data['id'] : 0;
		if ( ! $form_id ) {
			return;
		}
		$title  = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : 'Form ' . $form_id;
		$fields = array();
		foreach ( (array) $fields_in as $id => $f ) {
			$fields[] = array(
				'name'  => isset( $f['id'] ) ? 'field_' . $f['id'] : 'field_' . $id,
				'label' => isset( $f['name'] ) && '' !== $f['name'] ? (string) $f['name'] : 'Field ' . $id,
				'type'  => isset( $f['type'] ) ? (string) $f['type'] : '',
				'value' => isset( $f['value'] ) ? $f['value'] : '',
			);
		}
		$url   = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		$admin = $entry_id ? admin_url( 'admin.php?page=wpforms-entries&view=details&entry_id=' . (int) $entry_id ) : admin_url( 'admin.php?page=wpforms-overview' );
		self::submit( 'wpforms', $form_id, $title, $entry_id ? (string) $entry_id : null, $fields, $url, $admin );
	}

	/**
	 * Fluent Forms (fluentform/submission_inserted).
	 *
	 * @param int    $entry_id  Entry ID.
	 * @param array  $form_data Submitted data.
	 * @param object $form      Form model.
	 */
	public static function on_fluent( $entry_id, $form_data, $form ) {
		self::$fluent_done[ (int) $entry_id ] = true;
		self::fluent( $entry_id, $form_data, $form );
	}

	/** @var array<int,bool> entries already handled by the new hook */
	private static $fluent_done = array();

	public static function on_fluent_legacy( $entry_id, $form_data, $form ) {
		if ( isset( self::$fluent_done[ (int) $entry_id ] ) ) {
			return;
		}
		self::fluent( $entry_id, $form_data, $form );
	}

	private static function fluent( $entry_id, $form_data, $form ) {
		$form_id = is_object( $form ) && isset( $form->id ) ? (int) $form->id : 0;
		if ( ! $form_id ) {
			return;
		}
		$labels = array();
		$types  = array();
		if ( ! empty( $form->form_fields ) ) {
			$def = json_decode( (string) $form->form_fields, true );
			if ( isset( $def['fields'] ) && is_array( $def['fields'] ) ) {
				self::fluent_walk( $def['fields'], $labels, $types );
			}
		}
		$fields = array();
		foreach ( (array) $form_data as $name => $value ) {
			$name = (string) $name;
			if ( '' === $name || '_' === $name[0] ) {
				continue;
			}
			$fields[] = array(
				'name'  => $name,
				'label' => isset( $labels[ $name ] ) && '' !== $labels[ $name ] ? $labels[ $name ] : $name,
				'type'  => isset( $types[ $name ] ) ? $types[ $name ] : '',
				'value' => $value,
			);
		}
		$url = isset( $form_data['_wp_http_referer'] ) ? home_url( (string) $form_data['_wp_http_referer'] ) : '';
		self::submit( 'fluent', $form_id, isset( $form->title ) ? $form->title : 'Form ' . $form_id, (string) $entry_id, $fields, $url, admin_url( 'admin.php?page=fluent_forms&route=entries&form_id=' . $form_id . '#/entries/' . (int) $entry_id ) );
	}

	private static function fluent_walk( array $items, array &$labels, array &$types ) {
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['attributes']['name'] ) ) {
				$n            = (string) $item['attributes']['name'];
				$labels[ $n ] = isset( $item['settings']['label'] ) ? wp_strip_all_tags( (string) $item['settings']['label'] ) : '';
				if ( '' === $labels[ $n ] && isset( $item['element'] ) && 'input_name' === $item['element'] ) {
					$labels[ $n ] = 'Name';
				}
				$types[ $n ]  = isset( $item['element'] ) ? (string) $item['element'] : '';
			}
			if ( isset( $item['columns'] ) && is_array( $item['columns'] ) ) {
				foreach ( $item['columns'] as $col ) {
					if ( isset( $col['fields'] ) && is_array( $col['fields'] ) ) {
						self::fluent_walk( $col['fields'], $labels, $types );
					}
				}
			}
		}
	}

	/**
	 * Gravity Forms.
	 */
	public static function on_gravity( $entry, $form ) {
		if ( ! is_array( $entry ) || ! is_array( $form ) || ( isset( $entry['status'] ) && 'spam' === $entry['status'] ) ) {
			return;
		}
		$fields = array();
		foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
			if ( ! is_object( $field ) ) {
				continue;
			}
			$value = method_exists( $field, 'get_value_export' ) ? $field->get_value_export( $entry ) : ( $entry[ (string) $field->id ] ?? '' );
			$fields[] = array(
				'name'  => 'input_' . $field->id,
				'label' => isset( $field->label ) && '' !== $field->label ? (string) $field->label : 'Field ' . $field->id,
				'type'  => isset( $field->type ) ? (string) $field->type : '',
				'value' => $value,
			);
		}
		$form_id = (int) ( $form['id'] ?? 0 );
		$id      = isset( $entry['id'] ) ? (string) $entry['id'] : null;
		self::submit( 'gravity', $form_id, $form['title'] ?? 'Form ' . $form_id, $id, $fields, (string) ( $entry['source_url'] ?? '' ), admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $form_id . '&lid=' . (int) $id ) );
	}

	/* ------------------------------------------------------------------ */
	/* Normalisation                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Clean the fields and hand the submission to the event layer.
	 */
	public static function submit( $plugin, $form_id, $title, $entry_id, array $fields, $page_url, $admin_url ) {
		if ( ! self::is_enabled( $plugin, $form_id ) ) {
			return;
		}
		$clean = self::clean_fields( $fields );
		if ( empty( $clean ) ) {
			return;
		}
		Nextial_FE_Events::submitted(
			array(
				'plugin'      => (string) $plugin,
				'plugin_name' => self::source_label( $plugin ),
				'form_id'     => (string) $form_id,
				'form_title'  => wp_strip_all_tags( (string) $title ),
				'entry_id'    => null === $entry_id ? null : (string) $entry_id,
				'fields'      => $clean,
				'page_url'    => esc_url_raw( (string) $page_url ),
				'admin_url'   => (string) $admin_url,
			)
		);
	}

	/**
	 * @param array $fields Raw fields (name, label, type, value).
	 * @return array<int,array{name:string,label:string,value:string}>
	 */
	public static function clean_fields( array $fields ) {
		$exclude = self::excluded_fields();
		$out     = array();
		foreach ( $fields as $f ) {
			$type  = strtolower( (string) ( $f['type'] ?? '' ) );
			$name  = (string) ( $f['name'] ?? '' );
			$label = wp_strip_all_tags( (string) ( $f['label'] ?? $name ) );
			if ( in_array( $type, self::SENSITIVE_TYPES, true ) || preg_match( '/(password|passwd|card.?number|cvv|cvc|iban)/i', $name . ' ' . $label ) ) {
				continue;
			}
			if ( $exclude && ( in_array( strtolower( $name ), $exclude, true ) || in_array( strtolower( $label ), $exclude, true ) ) ) {
				continue;
			}
			$value = self::flatten( $f['value'] ?? '' );
			if ( '' === $value ) {
				continue;
			}
			if ( function_exists( 'mb_substr' ) ) {
				$value = mb_strlen( $value ) > self::MAX_VALUE ? mb_substr( $value, 0, self::MAX_VALUE ) . '…' : $value;
			} else {
				$value = strlen( $value ) > self::MAX_VALUE ? substr( $value, 0, self::MAX_VALUE ) . '...' : $value;
			}
			$out[] = array(
				'name'  => $name,
				'label' => '' === $label ? $name : $label,
				'value' => $value,
			);
			if ( count( $out ) >= self::MAX_FIELDS ) {
				break;
			}
		}
		/**
		 * Filter the cleaned fields before they are sent to ChatGPT.
		 *
		 * @param array $out    Cleaned fields.
		 * @param array $fields Raw fields from the form plugin.
		 */
		return apply_filters( 'nextial_fe_clean_fields', $out, $fields );
	}

	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();
			array_walk_recursive(
				$value,
				static function ( $v ) use ( &$parts ) {
					if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
						$parts[] = trim( (string) $v );
					}
				}
			);
			return implode( ', ', $parts );
		}
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		return is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
	}
}
