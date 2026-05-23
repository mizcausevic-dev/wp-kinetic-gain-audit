<?php
/**
 * Uninstall: drop the events table and remove options.
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-kg-audit-hasher.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-kg-audit-store.php';

KG_Audit_Store::uninstall();
delete_option( 'kg_audit_stream_url' );
