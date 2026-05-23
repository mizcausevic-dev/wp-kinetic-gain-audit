<?php
/**
 * Pure hash-chain logic for the Kinetic Gain audit log.
 *
 * No WordPress dependencies — this class is unit-testable with plain PHP and
 * implements the same canonical-JSON + SHA-256 chain convention as
 * audit-stream-py, so events produced here verify against the same rules
 * an auditor uses on the rest of the Kinetic Gain Protocol Suite.
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'KG_AUDIT_TESTING' ) ) {
	exit;
}

class KG_Audit_Hasher {

	/** SHA-256 of "nothing" — the prev_hash of the first event in a chain. */
	const ZERO_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

	/**
	 * Canonical JSON: recursively key-sorted, no superfluous whitespace,
	 * slashes + unicode unescaped. Deterministic across machines.
	 *
	 * @param mixed $data
	 * @return string
	 */
	public static function canonical_json( $data ) {
		$normalized = self::normalize( $data );
		return wp_json_encode_compat( $normalized );
	}

	/**
	 * Recursively sort associative-array keys so JSON output is stable.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	private static function normalize( $data ) {
		if ( is_array( $data ) ) {
			// Distinguish list (sequential) from map (assoc).
			$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
			if ( $is_list ) {
				return array_map( array( __CLASS__, 'normalize' ), $data );
			}
			ksort( $data );
			$out = array();
			foreach ( $data as $k => $v ) {
				$out[ $k ] = self::normalize( $v );
			}
			return $out;
		}
		return $data;
	}

	/**
	 * Compute the SHA-256 hash of an event body.
	 *
	 * The body must contain every field EXCEPT `hash` (it may contain
	 * `prev_hash` and `event_id`). This matches audit-stream-py: the hash
	 * covers the canonical JSON of all fields except `hash`.
	 *
	 * @param array $event_without_hash
	 * @return string 64-char lowercase hex
	 */
	public static function hash_event( array $event_without_hash ) {
		return hash( 'sha256', self::canonical_json( $event_without_hash ) );
	}

	/**
	 * Verify a full chain (ordered oldest→newest). Returns true if every
	 * event's prev_hash links to the prior event's hash and every hash
	 * recomputes correctly.
	 *
	 * @param array $events Each element: assoc array with keys event_id,
	 *                      timestamp, kind, source, payload, prev_hash, hash.
	 * @return bool
	 */
	public static function verify_chain( array $events ) {
		$expected_prev = self::ZERO_HASH;
		foreach ( $events as $event ) {
			if ( ! isset( $event['hash'], $event['prev_hash'] ) ) {
				return false;
			}
			if ( $event['prev_hash'] !== $expected_prev ) {
				return false;
			}
			$body = $event;
			// event_id is a store-assigned index, not part of the signed body
			// (it isn't known at append time under AUTO_INCREMENT); hash is the
			// field we're verifying. Exclude both, exactly as append() does.
			unset( $body['hash'], $body['event_id'] );
			if ( self::hash_event( $body ) !== $event['hash'] ) {
				return false;
			}
			$expected_prev = $event['hash'];
		}
		return true;
	}
}

/**
 * json_encode shim that works with or without WordPress loaded, with the
 * canonical flags. (wp_json_encode exists in WP; tests run without it.)
 */
function wp_json_encode_compat( $data ) {
	$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
	if ( function_exists( 'wp_json_encode' ) ) {
		return wp_json_encode( $data, $flags );
	}
	return json_encode( $data, $flags );
}
