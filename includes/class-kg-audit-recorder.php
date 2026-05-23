<?php
/**
 * Maps WordPress lifecycle events to Kinetic Gain governance events.
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KG_Audit_Recorder {

	/** Wire up the WordPress hooks we record. */
	public static function register() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_post_status' ), 10, 3 );
		add_action( 'activated_plugin', array( __CLASS__, 'on_plugin_activated' ), 10, 1 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'on_plugin_deactivated' ), 10, 1 );
		add_action( 'set_user_role', array( __CLASS__, 'on_user_role' ), 10, 3 );
		add_action( 'updated_option', array( __CLASS__, 'on_option_updated' ), 10, 3 );
	}

	private static function record( $kind, array $payload ) {
		$row = KG_Audit_Store::append( $kind, 'wp-kinetic-gain-audit', $payload );
		if ( $row ) {
			KG_Audit_Forwarder::maybe_forward( $row );
		}
	}

	public static function on_post_status( $new_status, $old_status, $post ) {
		if ( $new_status === $old_status ) {
			return;
		}
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return; // only care about transitions in/out of published
		}
		self::record(
			'content_published',
			array(
				'post_id'    => (int) $post->ID,
				'post_type'  => $post->post_type,
				'old_status' => $old_status,
				'new_status' => $new_status,
				'author'     => (int) $post->post_author,
			)
		);
	}

	public static function on_plugin_activated( $plugin ) {
		self::record( 'plugin_activated', array( 'plugin' => (string) $plugin ) );
	}

	public static function on_plugin_deactivated( $plugin ) {
		self::record( 'plugin_deactivated', array( 'plugin' => (string) $plugin ) );
	}

	public static function on_user_role( $user_id, $role, $old_roles ) {
		self::record(
			'user_role_changed',
			array(
				'user_id'   => (int) $user_id,
				'new_role'  => (string) $role,
				'old_roles' => array_values( (array) $old_roles ),
			)
		);
	}

	public static function on_option_updated( $option, $old_value, $value ) {
		// Only audit a curated allowlist of security-relevant options.
		$watched = array( 'users_can_register', 'default_role', 'siteurl', 'home', 'admin_email' );
		if ( ! in_array( $option, $watched, true ) ) {
			return;
		}
		self::record(
			'setting_changed',
			array(
				'option' => (string) $option,
				// Values intentionally omitted; we record THAT it changed, not the secret content.
			)
		);
	}
}
