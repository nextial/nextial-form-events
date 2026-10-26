<?php
/**
 * Admin screen: connection URL, self-test, subscriptions, delivery log.
 *
 * @package NextialFormEvents
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Missing -- Every admin-post handler calls self::guard(), which checks the capability and check_admin_referer() before reading $_POST.

class Nextial_FE_Admin {

	const SLUG       = 'nextial-form-events';
	/** Same capability that may approve an OAuth connection, so whoever connects can also see and disconnect. */
	public static function cap() {
		return 'manage_options';
	}
	const NOTICE_KEY = 'nextial_fe_notice_';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_post_nextial_fe_regenerate', array( __CLASS__, 'action_regenerate' ) );
		add_action( 'admin_post_nextial_fe_save_forms', array( __CLASS__, 'action_save_forms' ) );
		add_action( 'admin_post_nextial_fe_selftest', array( __CLASS__, 'action_selftest' ) );
		add_action( 'admin_post_nextial_fe_listen', array( __CLASS__, 'action_listen' ) );
		add_action( 'admin_post_nextial_fe_test_event', array( __CLASS__, 'action_test_event' ) );
		add_action( 'admin_post_nextial_fe_delete_sub', array( __CLASS__, 'action_delete_sub' ) );
		add_action( 'admin_post_nextial_fe_disconnect', array( __CLASS__, 'action_disconnect' ) );
		add_action( 'admin_post_nextial_fe_oauth_check', array( __CLASS__, 'action_oauth_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( NEXTIAL_FE_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/** Load the admin script only on the plugin page. */
	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_script( 'nextial-fe-admin', plugins_url( 'assets/js/admin.js', NEXTIAL_FE_FILE ), array(), NEXTIAL_FE_VERSION, true );
	}

	public static function menu() {
		add_options_page( 'Nextial Form Events', 'Nextial Form Events', self::cap(), self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function page_url() {
		return admin_url( 'options-general.php?page=' . self::SLUG );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'nextial-form-events' ) . '</a>' );
		return $links;
	}

	/**
	 * Product page URL (more events with the optional Pro add-on).
	 */
	const PRO_URL = 'https://nextial.it/form-events/';

	/**
	 * Whether an add-on has registered extra events.
	 */
	private static function has_addon_events() {
		return count( Nextial_FE_Events::definitions() ) > 1;
	}

	/**
	 * Plugins screen: a "More events" link while no add-on is active.
	 *
	 * @param string[] $meta Row meta links.
	 * @param string   $file Plugin basename.
	 */
	public static function row_meta( $meta, $file ) {
		if ( plugin_basename( NEXTIAL_FE_FILE ) === $file && ! self::has_addon_events() ) {
			$meta[] = '<a href="' . esc_url( self::PRO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Docs and support', 'nextial-form-events' ) . '</a>';
		}
		return $meta;
	}

	/* ------------------------------------------------------------------ */
	/* Actions                                                             */
	/* ------------------------------------------------------------------ */

	private static function guard( $action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Not allowed.', 'nextial-form-events' ) );
		}
		check_admin_referer( $action );
	}

	private static function done( $notice ) {
		set_transient( self::NOTICE_KEY . get_current_user_id(), $notice, 300 );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	public static function action_regenerate() {
		self::guard( 'nextial_fe_regenerate' );
		Nextial_FE_Store::regenerate_access_key();
		self::done(
			array(
				'type' => 'warning',
				'text' => __( 'New connection URL created. The old URL no longer works: update it in ChatGPT.', 'nextial-form-events' ),
			)
		);
	}

	public static function action_delete_sub() {
		self::guard( 'nextial_fe_delete_sub' );
		$id = isset( $_POST['sub'] ) ? sanitize_text_field( wp_unslash( $_POST['sub'] ) ) : '';
		Nextial_FE_Store::delete_subscription( $id );
		self::done(
			array(
				'type' => 'success',
				'text' => __( 'Subscription removed.', 'nextial-form-events' ),
			)
		);
	}

	public static function action_disconnect() {
		self::guard( 'nextial_fe_disconnect' );
		$client = isset( $_POST['client'] ) ? sanitize_text_field( wp_unslash( $_POST['client'] ) ) : '';
		$user   = isset( $_POST['user'] ) ? (int) $_POST['user'] : 0;
		Nextial_FE_OAuth::revoke_grant( $client, $user );
		self::done(
			array(
				'type' => 'success',
				'text' => __( 'Disconnected. Its access tokens and event subscriptions were removed.', 'nextial-form-events' ),
			)
		);
	}

	/**
	 * Fetch our own OAuth discovery documents over HTTP, the way ChatGPT
	 * will, to catch servers that block /.well-known/ or strip headers.
	 */
	public static function action_oauth_check() {
		self::guard( 'nextial_fe_oauth_check' );
		$steps = array();
		foreach (
			array(
				'Protected resource metadata' => array( Nextial_FE_OAuth::resource_metadata_url(), 'resource' ),
				'Authorization server metadata' => array( Nextial_FE_OAuth::as_metadata_url(), 'issuer' ),
			) as $label => $spec
		) {
			$res  = wp_remote_get( $spec[0], array( 'timeout' => 10, 'redirection' => 0 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			$doc  = is_wp_error( $res ) ? null : Nextial_FE_Input::json( (string) wp_remote_retrieve_body( $res ) );
			$steps[] = array(
				'label' => $label,
				'ok'    => 200 === $code && is_array( $doc ) && isset( $doc[ $spec[1] ] ),
				'info'  => is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . $code . ' ' . $spec[0],
			);
		}
		$res     = wp_remote_post(
			Nextial_FE_OAuth::resource(),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => '{"jsonrpc":"2.0","id":1,"method":"ping"}',
			)
		);
		$www     = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_header( $res, 'www-authenticate' );
		$steps[] = array(
			'label' => 'MCP endpoint asks for sign-in (401 + WWW-Authenticate)',
			'ok'    => ! is_wp_error( $res ) && 401 === (int) wp_remote_retrieve_response_code( $res ) && false !== strpos( $www, 'resource_metadata=' ),
			'info'  => is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ),
		);
		$ok = ! in_array( false, wp_list_pluck( $steps, 'ok' ), true );
		set_transient( 'nextial_fe_selftest_' . get_current_user_id(), $steps, 600 );
		self::done(
			array(
				'type' => $ok ? 'success' : 'error',
				'text' => $ok ? __( 'OAuth check passed: ChatGPT can discover the sign-in on this site.', 'nextial-form-events' ) : __( 'OAuth check failed. See the steps below.', 'nextial-form-events' ),
			)
		);
	}

	public static function action_test_event() {
		self::guard( 'nextial_fe_test_event' );
		$name = isset( $_POST['event'] ) ? sanitize_text_field( wp_unslash( $_POST['event'] ) ) : '';
		if ( ! array_key_exists( $name, Nextial_FE_Events::definitions() ) ) {
			self::done( array( 'type' => 'error', 'text' => 'Unknown event.' ) );
		}
		$sent = Nextial_FE_Dispatcher::emit( $name, Nextial_FE_Events::sample_payload( $name ), true );
		self::done(
			array(
				'type' => $sent ? 'success' : 'warning',
				/* translators: 1: number of subscribers, 2: event name */
				'text' => sprintf( __( 'Test event sent to %1$d subscriber(s) of %2$s. See the delivery log below.', 'nextial-form-events' ), $sent, $name ),
			)
		);
	}

	/**
	 * End-to-end self-test against the built-in receiver:
	 * subscribe (with real callback verification) -> deliver a signed event
	 * -> check the receiver accepted the signature -> unsubscribe.
	 */
	public static function action_selftest() {
		self::guard( 'nextial_fe_selftest' );
		$steps = self::run_selftest();
		$ok    = ! in_array( false, wp_list_pluck( $steps, 'ok' ), true );
		set_transient( 'nextial_fe_selftest_' . get_current_user_id(), $steps, 600 );
		self::done(
			array(
				'type' => $ok ? 'success' : 'error',
				'text' => $ok ? __( 'Self-test passed: subscription, callback verification, signed delivery and unsubscribe all work on this site.', 'nextial-form-events' ) : __( 'Self-test failed. See the steps below.', 'nextial-form-events' ),
			)
		);
	}

	/**
	 * Fresh key + secret for the built-in receiver. Any earlier receiver
	 * subscriptions stop verifying, so remove them.
	 *
	 * @return array{key:string,secret:string}
	 */
	private static function reset_receiver() {
		$cfg = array(
			'key'    => Nextial_FE_Store::random_token( 32 ),
			'secret' => Nextial_FE_Signer::generate_secret(),
		);
		foreach ( Nextial_FE_Store::all_subscriptions() as $s ) {
			if ( false !== strpos( $s['callback_url'], 'nextial-form-events/v1/receiver/' ) || false !== strpos( $s['callback_url'], 'nextial_fe_receiver=' ) ) {
				Nextial_FE_Store::delete_subscription( $s['id'] );
			}
		}
		update_option( Nextial_FE_Store::OPT_RECEIVER, $cfg, false );
		delete_option( Nextial_FE_Endpoints::OPT_RECEIVER_LOG );
		return $cfg;
	}

	/**
	 * Call our own MCP server in-process, exactly as a 2026-07-28 client would.
	 */
	private static function local_rpc( $principal, $method, array $params ) {
		$params['_meta'] = array(
			'io.modelcontextprotocol/protocolVersion'    => '2026-07-28',
			'io.modelcontextprotocol/clientInfo'         => array(
				'name'    => 'nextial-fe-admin',
				'version' => NEXTIAL_FE_VERSION,
			),
			'io.modelcontextprotocol/clientCapabilities' => new stdClass(),
		);
		$server          = new Nextial_FE_MCP_Server( $principal );
		return $server->handle(
			wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'id'      => wp_rand( 1, 99999 ),
					'method'  => $method,
					'params'  => $params,
				)
			),
			array(
				'mcp-protocol-version' => '2026-07-28',
				'mcp-method'           => $method,
			)
		);
	}

	/**
	 * Subscribe the built-in receiver to every event for 15 minutes, so a
	 * real form submission can be checked end to end.
	 */
	public static function action_listen() {
		self::guard( 'nextial_fe_listen' );
		$cfg  = self::reset_receiver();
		$ok   = array();
		$fail = '';
		foreach ( array_keys( Nextial_FE_Events::definitions() ) as $name ) {
			foreach ( array( Nextial_FE_Endpoints::receiver_url( $cfg['key'] ), Nextial_FE_Endpoints::receiver_fallback_url( $cfg['key'] ) ) as $url ) {
				list( , $res ) = self::local_rpc(
					'listen',
					'events/subscribe',
					array(
						'name'      => $name,
						'arguments' => new stdClass(),
						'delivery'  => array(
							'mode'   => 'webhook',
							'url'    => $url,
							'secret' => $cfg['secret'],
						),
						'cursor'    => null,
						'ttlMs'     => 900000,
					)
				);
				if ( isset( $res['result']['id'] ) ) {
					$ok[] = $name;
					break;
				}
				$fail = $res['error']['data']['reason'] ?? ( $res['error']['message'] ?? 'unknown' );
			}
		}
		self::done(
			$ok
				? array(
					'type' => 'success',
					/* translators: %s: event names */
					'text' => sprintf( __( 'Listening for 15 minutes to: %s. Submit one of your forms now, then reload this page: the receipts appear under "Test receiver".', 'nextial-form-events' ), implode( ', ', $ok ) ),
				)
				: array(
					'type' => 'error',
					/* translators: %s: failure reason */
					'text' => sprintf( __( 'Could not start listening: %s', 'nextial-form-events' ), $fail ),
				)
		);
	}

	public static function run_selftest() {
		$steps  = array();
		$cfg    = self::reset_receiver();
		$rkey   = $cfg['key'];
		$secret = $cfg['secret'];

		$call = static function ( $method, $params ) {
			return self::local_rpc( 'selftest', $method, $params );
		};

		// 1. Discovery.
		list( $st, $res ) = $call( 'server/discover', array() );
		$steps[]          = array(
			'label' => 'server/discover advertises events',
			'ok'    => 200 === $st && isset( $res['result']['capabilities']['events'] ),
			'info'  => 200 === $st ? 'HTTP 200' : 'HTTP ' . $st,
		);

		// 2. Subscribe, trying the REST URL first and the fallback URL second.
		$sub_id = null;
		$url    = null;
		foreach ( array( Nextial_FE_Endpoints::receiver_url( $rkey ), Nextial_FE_Endpoints::receiver_fallback_url( $rkey ) ) as $candidate ) {
			list( $st, $res ) = $call(
				'events/subscribe',
				array(
					'name'      => 'form.submitted',
					'arguments' => new stdClass(),
					'delivery'  => array(
						'mode'   => 'webhook',
						'url'    => $candidate,
						'secret' => $secret,
					),
					'cursor'    => null,
					'ttlMs'     => 600000,
				)
			);
			if ( isset( $res['result']['id'] ) ) {
				$sub_id = $res['result']['id'];
				$url    = $candidate;
				break;
			}
			$reason = isset( $res['error']['data']['reason'] ) ? $res['error']['data']['reason'] : ( $res['error']['message'] ?? 'unknown' );
			$steps[] = array(
				'label' => 'Callback verification via ' . ( false !== strpos( $candidate, '?nextial_fe_receiver' ) ? 'fallback URL' : 'REST URL' ),
				'ok'    => null,
				'info'  => 'Failed: ' . $reason,
			);
		}
		$steps[] = array(
			'label' => 'events/subscribe with callback challenge',
			'ok'    => (bool) $sub_id,
			'info'  => $sub_id ? $sub_id . ' (refreshBefore ' . $res['result']['refreshBefore'] . ')' : 'No verified callback URL. The site may be unable to call itself over HTTPS.',
		);
		if ( ! $sub_id ) {
			return $steps;
		}

		// 3. Signed delivery.
		$sub         = Nextial_FE_Store::get_subscription( $sub_id );
		$delivery_id = Nextial_FE_Dispatcher::emit_to( $sub, 'form.submitted', Nextial_FE_Events::sample_payload( 'form.submitted' ), true );
		$delivery    = Nextial_FE_Store::get_delivery( $delivery_id );
		$steps[]     = array(
			'label' => 'Signed event delivered (receiver answered 2xx)',
			'ok'    => $delivery && 'delivered' === $delivery['status'],
			'info'  => $delivery ? $delivery['status'] . ', HTTP ' . $delivery['last_code'] . ( $delivery['last_error'] ? ', ' . $delivery['last_error'] : '' ) : 'no delivery row',
		);

		// 4. Receiver-side signature check. The receiver wrote the log in
		// another request, so drop this request's cached copy (including the
		// "option does not exist" cache set by reset_receiver()) first.
		wp_cache_delete( Nextial_FE_Endpoints::OPT_RECEIVER_LOG, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$log     = get_option( Nextial_FE_Endpoints::OPT_RECEIVER_LOG, array() );
		$events  = array_values(
			array_filter(
				is_array( $log ) ? $log : array(),
				static function ( $e ) use ( $sub_id ) {
					return is_array( $e ) && 'event' === ( $e['type'] ?? '' ) && $sub_id === ( $e['subscription_id'] ?? '' );
				}
			)
		);
		$steps[] = array(
			'label' => 'Receiver verified the Standard Webhooks signature',
			'ok'    => ! empty( $events ) && ! empty( $events[0]['signature_ok'] ),
			'info'  => empty( $events ) ? 'nothing received' : ( $events[0]['signature_ok'] ? 'signature valid' : 'signature INVALID' ),
		);

		// 5. Unsubscribe (idempotent).
		list( $st, $res ) = $call(
			'events/unsubscribe',
			array(
				'name'      => 'form.submitted',
				'arguments' => new stdClass(),
				'delivery'  => array(
					'mode' => 'webhook',
					'url'  => $url,
				),
			)
		);
		$steps[] = array(
			'label' => 'events/unsubscribe removes the subscription',
			'ok'    => 200 === $st && null === Nextial_FE_Store::get_subscription( $sub_id ),
			'info'  => 'HTTP ' . $st,
		);
		return $steps;
	}

	/* ------------------------------------------------------------------ */
	/* Screen                                                              */
	/* ------------------------------------------------------------------ */

	private static function post_button( $action, $label, array $fields = array(), $class = 'button' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 6px 6px 0">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		foreach ( $fields as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
		}
		echo '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Save which forms are sent to ChatGPT.
	 */
	public static function action_save_forms() {
		self::guard( 'nextial_fe_save_forms' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$posted = isset( $_POST['forms'] ) && is_array( $_POST['forms'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['forms'] ) ) : array();
		$known  = array();
		foreach ( Nextial_FE_Forms::all_forms() as $f ) {
			$known[ $f['key'] ] = true;
		}
		$disabled = array();
		foreach ( array_keys( $known ) as $key ) {
			if ( ! in_array( $key, $posted, true ) ) {
				$disabled[] = $key;
			}
		}
		Nextial_FE_Forms::set_disabled( $disabled );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$exclude = isset( $_POST['exclude_fields'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_fields'] ) ) : '';
		Nextial_FE_Forms::set_excluded_fields( $exclude );
		self::done(
			array(
				'type' => 'success',
				'text' => __( 'Form settings saved.', 'nextial-form-events' ),
			)
		);
	}

	/**
	 * "Forms" section: which forms notify ChatGPT.
	 */
	private static function render_forms() {
		$forms = Nextial_FE_Forms::all_forms();
		?>
		<h2><?php esc_html_e( 'Forms sent to ChatGPT', 'nextial-form-events' ); ?></h2>
		<?php if ( empty( $forms ) ) : ?>
			<p><em><?php esc_html_e( 'No forms found yet. Create a form in your form plugin and reload this page.', 'nextial-form-events' ); ?></em></p>
			<?php
			return;
		endif;
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="nextial_fe_save_forms">
			<?php wp_nonce_field( 'nextial_fe_save_forms' ); ?>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th style="width:40px"><?php esc_html_e( 'Send', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Form', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Plugin', 'nextial-form-events' ); ?></th><th>ID</th></tr></thead>
				<tbody>
				<?php foreach ( $forms as $f ) : ?>
					<tr>
						<td><input type="checkbox" name="forms[]" value="<?php echo esc_attr( $f['key'] ); ?>" <?php checked( $f['enabled'] ); ?>></td>
						<td><?php echo esc_html( $f['title'] ); ?></td>
						<td><?php echo esc_html( Nextial_FE_Forms::source_label( $f['plugin'] ) ); ?></td>
						<td><code><?php echo esc_html( (string) $f['form_id'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<label for="nextial-fe-exclude"><strong><?php esc_html_e( 'Never send these fields', 'nextial-form-events' ); ?></strong></label><br>
				<input id="nextial-fe-exclude" type="text" class="regular-text" name="exclude_fields" value="<?php echo esc_attr( implode( ', ', Nextial_FE_Forms::excluded_fields() ) ); ?>" placeholder="phone, tax-id">
				<br><span class="description"><?php esc_html_e( 'Comma-separated field names or labels. Password, file-upload, credit-card and hidden technical fields are never sent.', 'nextial-form-events' ); ?></span>
			</p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save form settings', 'nextial-form-events' ); ?></button></p>
		</form>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( self::cap() ) ) {
			return;
		}
		$uid    = get_current_user_id();
		$notice = get_transient( self::NOTICE_KEY . $uid );
		delete_transient( self::NOTICE_KEY . $uid );
		$steps = get_transient( 'nextial_fe_selftest_' . $uid );
		delete_transient( 'nextial_fe_selftest_' . $uid );
		$fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap nextial-fe">
			<h1>Nextial Form Events <span style="font-size:13px;color:#646970">v<?php echo esc_html( NEXTIAL_FE_VERSION ); ?></span></h1>
			<?php $nextial_fe_event_names = array_keys( Nextial_FE_Events::definitions() ); ?>
			<?php if ( count( $nextial_fe_event_names ) > 1 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: number of available events. */
						esc_html__( 'Lets ChatGPT react to what happens on this site. %d events are available:', 'nextial-form-events' ),
						(int) count( $nextial_fe_event_names )
					);
					foreach ( $nextial_fe_event_names as $nextial_fe_i => $nextial_fe_name ) {
						echo ( $nextial_fe_i ? ', ' : ' ' ) . '<code>' . esc_html( $nextial_fe_name ) . '</code>';
					}
					echo '. ';
					esc_html_e( 'ChatGPT subscribes through MCP Events and receives a signed notification each time.', 'nextial-form-events' );
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Lets ChatGPT react to every new form submission on this site (Contact Form 7, WPForms, Fluent Forms, Gravity Forms). ChatGPT subscribes through MCP Events and receives a signed notification each time.', 'nextial-form-events' ); ?></p>
			<?php endif; ?>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! Nextial_FE_Forms::active_sources() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'No supported form plugin is active. Install Contact Form 7, WPForms, Fluent Forms or Gravity Forms.', 'nextial-form-events' ); ?></p></div>
			<?php endif; ?>

			<?php self::render_forms(); ?>

			<h2><?php esc_html_e( '1. Connect ChatGPT', 'nextial-form-events' ); ?></h2>
			<?php if ( Nextial_FE_OAuth::available() ) : ?>
				<ol>
					<li><?php esc_html_e( 'In ChatGPT open Plugins → Add → "Add custom MCP server".', 'nextial-form-events' ); ?></li>
					<li><?php esc_html_e( 'Paste this URL and choose Authentication "OAuth".', 'nextial-form-events' ); ?></li>
					<li><?php esc_html_e( 'ChatGPT opens this site: sign in and press "Allow". Then, in a Work chat, ask for example "Tell me here whenever someone fills in a form on my site".', 'nextial-form-events' ); ?></li>
				</ol>
				<p><input type="text" readonly class="large-text code nextial-fe-select" id="nextial-fe-url" value="<?php echo esc_attr( Nextial_FE_OAuth::resource() ); ?>"></p>
				<p>
					<button type="button" class="button button-primary" data-nextial-fe-copy="nextial-fe-url" data-nextial-fe-copied="<?php esc_attr_e( 'Copied', 'nextial-form-events' ); ?>"><?php esc_html_e( 'Copy URL', 'nextial-form-events' ); ?></button>
					<?php self::post_button( 'nextial_fe_oauth_check', __( 'Check OAuth setup', 'nextial-form-events' ), array(), 'button button-secondary' ); ?>
				</p>
				<?php $conns = Nextial_FE_OAuth::connections(); ?>
				<?php if ( $conns ) : ?>
					<h3><?php esc_html_e( 'Connected apps', 'nextial-form-events' ); ?></h3>
					<table class="widefat striped" style="max-width:900px">
						<thead><tr><th><?php esc_html_e( 'App', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Approved by', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Last token', 'nextial-form-events' ); ?></th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $conns as $c ) : ?>
							<?php $u = get_userdata( $c['user_id'] ); ?>
							<tr>
								<td><?php echo esc_html( $c['client_name'] ); ?></td>
								<td><?php echo esc_html( $u ? $u->display_name : '#' . $c['user_id'] ); ?></td>
								<td><?php echo esc_html( wp_date( $fmt, $c['last_seen'] ) ); ?></td>
								<td><?php self::post_button( 'nextial_fe_disconnect', __( 'Disconnect', 'nextial-form-events' ), array( 'client' => $c['client_id'], 'user' => $c['user_id'] ), 'button-link-delete button-link' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php $oauth_log = Nextial_FE_OAuth::recent_log(); ?>
				<?php if ( $oauth_log ) : ?>
					<details>
						<summary><?php esc_html_e( 'Recent sign-in problems', 'nextial-form-events' ); ?> (<?php echo esc_html( (string) count( $oauth_log ) ); ?>)</summary>
						<table class="widefat striped" style="max-width:900px">
							<thead><tr><th><?php esc_html_e( 'When', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Where', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'App', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Detail', 'nextial-form-events' ); ?></th></tr></thead>
							<tbody>
							<?php foreach ( $oauth_log as $entry ) : ?>
								<tr>
									<td><?php echo esc_html( wp_date( $fmt, (int) $entry['at'] ) ); ?></td>
									<td><code><?php echo esc_html( $entry['kind'] ); ?></code></td>
									<td><?php echo esc_html( $entry['client'] ); ?></td>
									<td><?php echo esc_html( $entry['detail'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php endif; ?>
			<?php else : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'Sign-in with OAuth needs pretty permalinks.', 'nextial-form-events' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php esc_html_e( 'Choose any setting other than "Plain" in Settings → Permalinks.', 'nextial-form-events' ); ?></a>
					<?php esc_html_e( 'Until then you can use the secret URL below.', 'nextial-form-events' ); ?>
				</p></div>
			<?php endif; ?>
			<details <?php echo Nextial_FE_OAuth::available() ? '' : 'open'; ?>>
				<summary><?php esc_html_e( 'Alternative: secret URL without sign-in (for testing)', 'nextial-form-events' ); ?></summary>
				<p><?php esc_html_e( 'Choose "No authentication" in ChatGPT: the URL itself contains a secret key, so keep it private.', 'nextial-form-events' ); ?></p>
				<p><input type="text" readonly class="large-text code nextial-fe-select" id="nextial-fe-key-url" value="<?php echo esc_attr( Nextial_FE_Endpoints::mcp_url() ); ?>"></p>
				<p><button type="button" class="button" data-nextial-fe-copy="nextial-fe-key-url" data-nextial-fe-copied="<?php esc_attr_e( 'Copied', 'nextial-form-events' ); ?>"><?php esc_html_e( 'Copy secret URL', 'nextial-form-events' ); ?></button></p>
				<p><?php esc_html_e( 'If your site blocks /wp-json/:', 'nextial-form-events' ); ?></p>
				<p><input type="text" readonly class="large-text code nextial-fe-select" value="<?php echo esc_attr( Nextial_FE_Endpoints::mcp_fallback_url() ); ?>"></p>
				<p><?php self::post_button( 'nextial_fe_regenerate', __( 'Create a new secret URL (revokes the old one)', 'nextial-form-events' ) ); ?></p>
			</details>

			<h2><?php esc_html_e( '2. Self-test', 'nextial-form-events' ); ?></h2>
			<p><?php esc_html_e( 'Runs the full loop on this site with a built-in test receiver: subscribe, verify the callback, send a signed event, check the signature, unsubscribe. Nothing is sent to ChatGPT.', 'nextial-form-events' ); ?></p>
			<p><?php self::post_button( 'nextial_fe_selftest', __( 'Run self-test', 'nextial-form-events' ), array(), 'button button-secondary' ); ?></p>
			<?php if ( is_array( $steps ) ) : ?>
				<table class="widefat striped" style="max-width:900px">
					<tbody>
					<?php foreach ( $steps as $s ) : ?>
						<tr>
							<td style="width:30px"><?php echo true === $s['ok'] ? '✅' : ( false === $s['ok'] ? '❌' : '⚠️' ); ?></td>
							<td><?php echo esc_html( $s['label'] ); ?></td>
							<td><code><?php echo esc_html( $s['info'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p><?php esc_html_e( 'To check real activity, start listening, then submit one of your forms: each submission should appear below with a valid signature.', 'nextial-form-events' ); ?></p>
			<p><?php self::post_button( 'nextial_fe_listen', __( 'Listen for real form submissions (15 min)', 'nextial-form-events' ), array(), 'button button-secondary' ); ?></p>
			<?php $receipts = get_option( Nextial_FE_Endpoints::OPT_RECEIVER_LOG, array() ); ?>
			<?php if ( is_array( $receipts ) && $receipts ) : ?>
				<h3><?php esc_html_e( 'Test receiver', 'nextial-form-events' ); ?></h3>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th><?php esc_html_e( 'When', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Type', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Event', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Signature', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Body', 'nextial-form-events' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $receipts as $r ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( $fmt, (int) $r['at'] ) ); ?></td>
							<td><?php echo esc_html( $r['type'] ); ?></td>
							<td><code><?php echo esc_html( $r['name'] ); ?></code></td>
							<td><?php echo $r['signature_ok'] ? '✅' : '❌'; ?></td>
							<td><details><summary><?php esc_html_e( 'show', 'nextial-form-events' ); ?></summary><pre style="white-space:pre-wrap;max-width:700px"><?php echo esc_html( $r['body'] ); ?></pre></details></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( '3. Active subscriptions', 'nextial-form-events' ); ?></h2>
			<?php $subs = Nextial_FE_Store::all_subscriptions( true ); ?>
			<?php if ( empty( $subs ) ) : ?>
				<p><em><?php esc_html_e( 'None yet. In ChatGPT, ask for example: "When someone fills in my contact form, tell me if it is a real lead and draft a reply."', 'nextial-form-events' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th><?php esc_html_e( 'Event', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Filters', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Receiver', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Expires', 'nextial-form-events' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php foreach ( $subs as $s ) : ?>
						<tr>
							<td><code><?php echo esc_html( $s['event_name'] ); ?></code></td>
							<td><code><?php echo esc_html( empty( $s['arguments'] ) ? '—' : wp_json_encode( $s['arguments'] ) ); ?></code></td>
							<td><?php echo esc_html( (string) wp_parse_url( $s['callback_url'], PHP_URL_HOST ) ); ?></td>
							<td><?php echo null === $s['expires_at'] ? esc_html__( 'never', 'nextial-form-events' ) : esc_html( wp_date( $fmt, (int) floor( $s['expires_at'] / 1000 ) ) ); ?></td>
							<td><?php self::post_button( 'nextial_fe_delete_sub', __( 'Remove', 'nextial-form-events' ), array( 'sub' => $s['id'] ), 'button-link-delete button-link' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p>
				<?php
				foreach ( array_keys( Nextial_FE_Events::definitions() ) as $name ) {
					/* translators: %s: event name */
					self::post_button( 'nextial_fe_test_event', sprintf( __( 'Send test %s', 'nextial-form-events' ), $name ), array( 'event' => $name ) );
				}
				?>
				</p>
			<?php endif; ?>

			<h2><?php esc_html_e( '4. Recent deliveries', 'nextial-form-events' ); ?></h2>
			<?php $dels = Nextial_FE_Store::recent_deliveries( 20 ); ?>
			<?php if ( empty( $dels ) ) : ?>
				<p><em><?php esc_html_e( 'No deliveries yet.', 'nextial-form-events' ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1100px">
					<thead><tr><th><?php esc_html_e( 'When', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Event', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Status', 'nextial-form-events' ); ?></th><th><?php esc_html_e( 'Attempts', 'nextial-form-events' ); ?></th><th>HTTP</th><th><?php esc_html_e( 'Detail', 'nextial-form-events' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $dels as $d ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( $fmt, (int) $d['created_at'] ) ); ?></td>
							<td><code><?php echo esc_html( $d['event_name'] ); ?></code></td>
							<td><?php echo esc_html( $d['status'] ); ?></td>
							<td><?php echo esc_html( $d['attempts'] ); ?></td>
							<td><?php echo esc_html( (string) $d['last_code'] ); ?></td>
							<td><?php echo esc_html( (string) $d['last_error'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
