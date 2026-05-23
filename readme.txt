=== Kinetic Gain Audit ===
Contributors: mizcausevic
Tags: audit log, security, governance, compliance, activity log
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tamper-evident, MySQL-backed governance audit log for WordPress. SHA-256 hash chain, one-click verify, optional forwarding to the Kinetic Gain audit-stream spine.

== Description ==

Most WordPress activity-log plugins write rows you have to trust. Kinetic Gain Audit writes a **tamper-evident hash chain**: every event is linked to the one before it by a SHA-256 hash, so altering, deleting, or inserting a row anywhere in the history is mathematically detectable. One click on the admin screen re-walks the chain and tells you if it is intact.

It records the governance moments that matter:

* Content published / unpublished (post + author + type)
* Plugins activated / deactivated
* User role changes
* Security-relevant setting changes (registration, default role, site URL, admin email)

Events are stored in a dedicated MySQL table using the same canonical-JSON + SHA-256 convention as the open [audit-stream-py](https://github.com/mizcausevic-dev/audit-stream-py) spec, so an auditor verifies your WordPress log with the exact rules they use across the rest of the Kinetic Gain Protocol Suite.

= Lite (this plugin) =

* MySQL hash-chained event log
* 5 recorded event kinds
* Admin event viewer + one-click chain verification
* Optional best-effort forward to an audit-stream-py endpoint

= Pro (roadmap) =

* Configurable event kinds + custom hooks
* CSV / JSON export of the verified chain
* Scheduled off-site chain anchoring (publish the latest hash to a well-known URL)
* Multisite network-wide aggregation
* WP-CLI `wp kg-audit verify` command

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wp-kinetic-gain-audit/`.
2. Activate it through the *Plugins* screen. The events table is created on activation.
3. Visit *Tools → KG Audit* to view the log and verify the chain.
4. (Optional) Paste an audit-stream-py `/events` URL to forward events off-site.

== Frequently Asked Questions ==

= Does this slow down my site? =

No. Events are single indexed INSERTs; off-site forwarding is non-blocking with a 2-second timeout.

= What happens on uninstall? =

The events table and the plugin option are dropped. Export first if you need to retain the log.

== Changelog ==

= 0.1.0 =
* Initial release: MySQL hash-chained audit log, 5 event kinds, admin viewer + verify, optional audit-stream-py forwarding.
