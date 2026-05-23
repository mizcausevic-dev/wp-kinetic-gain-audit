<?php
/**
 * Admin screen: event log table + "Verify chain" + audit-stream URL setting.
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KG_Audit_Admin {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
	}

	public static function menu() {
		add_management_page(
			__( 'Kinetic Gain Audit', 'wp-kinetic-gain-audit' ),
			__( 'KG Audit', 'wp-kinetic-gain-audit' ),
			'manage_options',
			'kg-audit',
			array( __CLASS__, 'render' )
		);
	}

	public static function settings() {
		register_setting( 'kg_audit', KG_Audit_Forwarder::OPTION, array( 'sanitize_callback' => 'esc_url_raw' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$events    = KG_Audit_Store::all_ordered();
		$verified  = KG_Audit_Hasher::verify_chain( $events );
		$recent    = array_reverse( $events ); // newest first for display
		$recent    = array_slice( $recent, 0, 100 );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Kinetic Gain Audit', 'wp-kinetic-gain-audit' ) . '</h1>';

		// Chain integrity badge.
		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No events recorded yet.', 'wp-kinetic-gain-audit' ) . '</p>';
		} elseif ( $verified ) {
			echo '<p style="color:#057a55;font-weight:600;">✓ ' .
				esc_html( sprintf(
					/* translators: %d: number of events */
					__( 'Chain verified — %d events, tamper-evident hash chain intact.', 'wp-kinetic-gain-audit' ),
					count( $events )
				) ) . '</p>';
		} else {
			echo '<p style="color:#d63638;font-weight:700;">✗ ' .
				esc_html__( 'CHAIN BROKEN — an event has been altered, deleted, or inserted out of band.', 'wp-kinetic-gain-audit' ) . '</p>';
		}

		// Settings form (audit-stream URL).
		echo '<form method="post" action="options.php" style="margin:1em 0;">';
		settings_fields( 'kg_audit' );
		$url = esc_attr( get_option( KG_Audit_Forwarder::OPTION, '' ) );
		echo '<label><strong>' . esc_html__( 'audit-stream-py URL (optional):', 'wp-kinetic-gain-audit' ) . '</strong><br/>';
		echo '<input type="url" name="' . esc_attr( KG_Audit_Forwarder::OPTION ) . '" value="' . $url . '" class="regular-text" placeholder="https://audit.internal/events" /></label> ';
		submit_button( __( 'Save', 'wp-kinetic-gain-audit' ), 'secondary', 'submit', false );
		echo '</form>';

		// Event table.
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>#</th><th>' . esc_html__( 'Time (UTC)', 'wp-kinetic-gain-audit' ) . '</th><th>' . esc_html__( 'Kind', 'wp-kinetic-gain-audit' ) . '</th><th>' . esc_html__( 'Payload', 'wp-kinetic-gain-audit' ) . '</th><th>' . esc_html__( 'Hash', 'wp-kinetic-gain-audit' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $recent as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( $row['event_id'] ) . '</td>';
			echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
			echo '<td><code>' . esc_html( $row['kind'] ) . '</code></td>';
			echo '<td><code>' . esc_html( wp_json_encode( $row['payload'] ) ) . '</code></td>';
			echo '<td><code>' . esc_html( substr( (string) $row['hash'], 0, 12 ) ) . '…</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}
}
