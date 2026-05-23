<?php
/**
 * MySQL-backed, hash-chained event store (via $wpdb).
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KG_Audit_Store {

	/** @return string Fully-qualified table name including the site prefix. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'kg_audit_events';
	}

	/** Create the events table. Called on activation. */
	public static function install() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			kind VARCHAR(128) NOT NULL,
			source VARCHAR(191) NOT NULL,
			payload LONGTEXT NOT NULL,
			prev_hash CHAR(64) NOT NULL,
			hash CHAR(64) NOT NULL,
			PRIMARY KEY  (event_id),
			KEY kind (kind),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/** Drop the table. Called from uninstall.php. */
	public static function uninstall() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/** @return string The hash of the most recent event, or ZERO_HASH if empty. */
	public static function latest_hash() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hash = $wpdb->get_var( "SELECT hash FROM {$table} ORDER BY event_id DESC LIMIT 1" );
		return $hash ? $hash : KG_Audit_Hasher::ZERO_HASH;
	}

	/**
	 * Append one event to the chain. Computes prev_hash + hash, inserts,
	 * returns the stored row (assoc) or null on failure.
	 *
	 * @param string $kind
	 * @param string $source
	 * @param array  $payload
	 * @return array|null
	 */
	public static function append( $kind, $source, array $payload ) {
		global $wpdb;
		$table     = self::table();
		$prev_hash = self::latest_hash();
		$created   = gmdate( 'Y-m-d\TH:i:s\Z' );

		// The hash body excludes event_id (assigned by DB) and hash itself,
		// matching audit-stream-py's "all fields except hash" for the chain.
		$body = array(
			'created_at' => $created,
			'kind'       => $kind,
			'source'     => $source,
			'payload'    => $payload,
			'prev_hash'  => $prev_hash,
		);
		$hash = KG_Audit_Hasher::hash_event( $body );

		$ok = $wpdb->insert(
			$table,
			array(
				'created_at' => $created,
				'kind'       => $kind,
				'source'     => $source,
				'payload'    => wp_json_encode( $payload ),
				'prev_hash'  => $prev_hash,
				'hash'       => $hash,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return null;
		}

		return array(
			'event_id'   => (int) $wpdb->insert_id,
			'created_at' => $created,
			'kind'       => $kind,
			'source'     => $source,
			'payload'    => $payload,
			'prev_hash'  => $prev_hash,
			'hash'       => $hash,
		);
	}

	/**
	 * Fetch recent events (newest first) for the admin view.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function recent( $limit = 100 ) {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 1000, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY event_id DESC LIMIT %d", $limit ), ARRAY_A );
		return $rows ? $rows : array();
	}

	/**
	 * Fetch the full chain oldest→newest for verification.
	 *
	 * @return array
	 */
	public static function all_ordered() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY event_id ASC", ARRAY_A );
		if ( ! $rows ) {
			return array();
		}
		// Decode payload JSON back to arrays so verify_chain re-hashes identically.
		foreach ( $rows as &$row ) {
			$row['payload']  = json_decode( $row['payload'], true );
			$row['event_id'] = (int) $row['event_id'];
		}
		return $rows;
	}
}
