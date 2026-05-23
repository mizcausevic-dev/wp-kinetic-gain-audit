<?php
/**
 * Plugin Name:       Kinetic Gain Audit
 * Plugin URI:        https://github.com/mizcausevic-dev/wp-kinetic-gain-audit
 * Description:       Tamper-evident, MySQL-backed governance audit log for WordPress. Records publishes, plugin/role/setting changes into a SHA-256 hash chain (audit-stream-py compatible) with a one-click chain-verify and optional forwarding to the Kinetic Gain audit-stream spine.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Miz Causevic / Kinetic Gain LLC
 * Author URI:        https://kineticgain.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-kinetic-gain-audit
 *
 * @package KineticGain\Audit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KG_AUDIT_VERSION', '0.1.0' );
define( 'KG_AUDIT_DIR', plugin_dir_path( __FILE__ ) );

require_once KG_AUDIT_DIR . 'includes/class-kg-audit-hasher.php';
require_once KG_AUDIT_DIR . 'includes/class-kg-audit-store.php';
require_once KG_AUDIT_DIR . 'includes/class-kg-audit-forwarder.php';
require_once KG_AUDIT_DIR . 'includes/class-kg-audit-recorder.php';
require_once KG_AUDIT_DIR . 'includes/class-kg-audit-admin.php';

register_activation_hook( __FILE__, array( 'KG_Audit_Store', 'install' ) );

add_action(
	'plugins_loaded',
	function () {
		KG_Audit_Recorder::register();
		if ( is_admin() ) {
			KG_Audit_Admin::register();
		}
	}
);
