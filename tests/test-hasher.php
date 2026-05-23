<?php
/**
 * Pure-PHP unit test for KG_Audit_Hasher — runs without WordPress.
 *
 *   php tests/test-hasher.php
 *
 * Exits 0 if all assertions pass, 1 otherwise.
 *
 * @package KineticGain\Audit
 */

define( 'KG_AUDIT_TESTING', true );
require_once __DIR__ . '/../includes/class-kg-audit-hasher.php';

$failures = 0;
$tests    = 0;

function check( $label, $cond ) {
	global $failures, $tests;
	$tests++;
	if ( $cond ) {
		echo "  ok   - {$label}\n";
	} else {
		echo "  FAIL - {$label}\n";
		$failures++;
	}
}

/** Build a valid chain of N events using the same body convention as the store. */
function build_chain( $kinds ) {
	$events = array();
	$prev   = KG_Audit_Hasher::ZERO_HASH;
	$i      = 1;
	foreach ( $kinds as $kind ) {
		$body = array(
			'created_at' => '2026-05-21T00:00:0' . $i . 'Z',
			'kind'       => $kind,
			'source'     => 'wp-kinetic-gain-audit',
			'payload'    => array( 'n' => $i ),
			'prev_hash'  => $prev,
		);
		$hash            = KG_Audit_Hasher::hash_event( $body );
		$event           = $body;
		$event['hash']   = $hash;
		$event['event_id'] = $i; // store-assigned; must NOT affect verification
		$events[]        = $event;
		$prev            = $hash;
		$i++;
	}
	return $events;
}

// 1. canonical_json is key-sorted + stable regardless of input key order.
$a = KG_Audit_Hasher::canonical_json( array( 'b' => 1, 'a' => 2 ) );
$b = KG_Audit_Hasher::canonical_json( array( 'a' => 2, 'b' => 1 ) );
check( 'canonical_json sorts keys deterministically', $a === $b && $a === '{"a":2,"b":1}' );

// 2. canonical_json preserves list order (does not sort arrays).
$list = KG_Audit_Hasher::canonical_json( array( 3, 1, 2 ) );
check( 'canonical_json preserves list order', $list === '[3,1,2]' );

// 3. nested maps are recursively sorted.
$nested = KG_Audit_Hasher::canonical_json( array( 'z' => array( 'y' => 1, 'x' => 2 ), 'a' => 3 ) );
check( 'canonical_json recurses', $nested === '{"a":3,"z":{"x":2,"y":1}}' );

// 4. hash_event is 64 hex chars.
$h = KG_Audit_Hasher::hash_event( array( 'kind' => 'x', 'prev_hash' => KG_Audit_Hasher::ZERO_HASH ) );
check( 'hash_event returns 64-char hex', strlen( $h ) === 64 && ctype_xdigit( $h ) );

// 5. a well-formed chain verifies.
$chain = build_chain( array( 'content_published', 'plugin_activated', 'user_role_changed' ) );
check( 'valid chain verifies', KG_Audit_Hasher::verify_chain( $chain ) === true );

// 6. event_id does NOT affect verification (store-assigned).
$chain2 = build_chain( array( 'a', 'b' ) );
$chain2[0]['event_id'] = 9999;
check( 'event_id is excluded from the signed body', KG_Audit_Hasher::verify_chain( $chain2 ) === true );

// 7. tampering with a payload breaks verification.
$tampered = build_chain( array( 'a', 'b', 'c' ) );
$tampered[1]['payload'] = array( 'n' => 999 );
check( 'tampered payload breaks the chain', KG_Audit_Hasher::verify_chain( $tampered ) === false );

// 8. deleting an event breaks the chain (prev_hash linkage fails).
$deleted = build_chain( array( 'a', 'b', 'c' ) );
unset( $deleted[1] );
$deleted = array_values( $deleted );
check( 'deleting an event breaks the chain', KG_Audit_Hasher::verify_chain( $deleted ) === false );

// 9. reordering breaks the chain.
$reordered = build_chain( array( 'a', 'b', 'c' ) );
$tmp                = $reordered[0];
$reordered[0]       = $reordered[1];
$reordered[1]       = $tmp;
check( 'reordering breaks the chain', KG_Audit_Hasher::verify_chain( $reordered ) === false );

// 10. empty chain is trivially valid.
check( 'empty chain verifies', KG_Audit_Hasher::verify_chain( array() ) === true );

echo "\n{$tests} tests, {$failures} failures\n";
exit( $failures === 0 ? 0 : 1 );
