<?php
/**
 * Optional best-effort forward to audit-stream-py.
 *
 * If the "kg_audit_stream_url" option is set, each recorded event is POSTed
 * to that endpoint using the audit-stream-py PublishRequest shape. Failures
 * are swallowed (best-effort; never blocks the WP request).
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KG_Audit_Forwarder {

	const OPTION = 'kg_audit_stream_url';

	/**
	 * @param array $row A stored event row from KG_Audit_Store::append().
	 */
	public static function maybe_forward( array $row ) {
		$url = get_option( self::OPTION, '' );
		if ( empty( $url ) ) {
			return;
		}

		$event = array(
			'kind'    => $row['kind'],
			'source'  => $row['source'],
			'payload' => array_merge(
				is_array( $row['payload'] ) ? $row['payload'] : array(),
				array(
					'wp_event_id' => $row['event_id'],
					'hash'        => $row['hash'],
					'prev_hash'   => $row['prev_hash'],
				)
			),
		);

		// Fire-and-forget: short timeout, non-blocking, errors ignored.
		wp_remote_post(
			$url,
			array(
				'timeout'  => 2,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $event ),
			)
		);
	}
}
