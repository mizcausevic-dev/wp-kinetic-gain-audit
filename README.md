# wp-kinetic-gain-audit

> A WordPress plugin that writes a **tamper-evident, MySQL-backed governance audit log** — every publish, plugin toggle, role change, and security-setting change linked into a SHA-256 hash chain you can verify in one click.

```
Tools → KG Audit
  ✓ Chain verified — 1,284 events, tamper-evident hash chain intact.
```

The MySQL-lane member of the [Kinetic Gain Protocol Suite](https://github.com/mizcausevic-dev/kinetic-gain-protocol-suite) — it brings the Suite's tamper-evident `audit-stream-py` convention to the 40%+ of the web that runs on WordPress + MySQL.

## Why it's different from every other activity-log plugin

Most WordPress audit-log plugins write rows you have to *trust*. This one writes a **hash chain**: each event stores a SHA-256 hash over its own canonical JSON plus the previous event's hash. Altering a row, deleting one, or inserting one out of band breaks the chain — and the admin screen's one-click verifier detects it mathematically.

The chain uses the **same canonical-JSON + SHA-256 convention as [audit-stream-py](https://github.com/mizcausevic-dev/audit-stream-py)**, so an auditor verifies your WordPress log with the exact rules they use across the rest of the Suite — and you can forward every event to a central audit-stream-py spine for a portfolio-wide verifiable narrative.

## What it records

| WordPress hook | Event kind | Payload |
| --- | --- | --- |
| `transition_post_status` (in/out of published) | `content_published` | post_id, post_type, old/new status, author |
| `activated_plugin` | `plugin_activated` | plugin |
| `deactivated_plugin` | `plugin_deactivated` | plugin |
| `set_user_role` | `user_role_changed` | user_id, new_role, old_roles |
| `updated_option` (allowlisted) | `setting_changed` | option name (value omitted by design) |

## The MySQL schema

```sql
CREATE TABLE wp_kg_audit_events (
  event_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL,
  kind       VARCHAR(128) NOT NULL,
  source     VARCHAR(191) NOT NULL,
  payload    LONGTEXT NOT NULL,
  prev_hash  CHAR(64) NOT NULL,
  hash       CHAR(64) NOT NULL,
  KEY kind (kind),
  KEY created_at (created_at)
);
```

`hash = sha256(canonical_json({created_at, kind, source, payload, prev_hash}))`. `event_id` is a store-assigned index and is **not** part of the signed body (it isn't known under `AUTO_INCREMENT` at append time) — verification excludes it, exactly as the append path does.

## Architecture

```
WP hook ──► KG_Audit_Recorder ──► KG_Audit_Store.append()
                                      │  computes prev_hash + hash
                                      ▼
                              wp_kg_audit_events (MySQL)
                                      │
                                      ├──► KG_Audit_Admin   (view + verify_chain)
                                      └──► KG_Audit_Forwarder ──► audit-stream-py (optional, non-blocking)
```

`KG_Audit_Hasher` is pure PHP — no WordPress dependency — so the chain logic is unit-tested without a WP runtime (`php tests/test-hasher.php`).

## Install

1. Copy to `wp-content/plugins/wp-kinetic-gain-audit/`.
2. Activate — the events table is created on activation.
3. *Tools → KG Audit* to view + verify.
4. Optional: paste an `audit-stream-py` `/events` URL to forward events off-site (best-effort, non-blocking).

## Develop / test

```bash
php tests/test-hasher.php      # pure-PHP hash-chain tests, no WordPress needed
find . -name '*.php' | xargs -n1 php -l   # syntax-lint everything
```

CI runs `php -l` across PHP 8.0/8.1/8.2/8.3 and the hasher unit test on every push.

## Lite vs Pro

This repo is the **Lite** edition (free, GPL). The **Pro** roadmap: configurable event kinds + custom hooks, verified CSV/JSON export, scheduled off-site hash anchoring to a well-known URL, multisite aggregation, and a `wp kg-audit verify` WP-CLI command. See [the WordPress plugin business plan](https://kineticgain.com/) for the productization model.

## Composes with

| Concern | Repo |
| --- | --- |
| The tamper-evident spine | [`audit-stream-py`](https://github.com/mizcausevic-dev/audit-stream-py) |
| Postgres sibling (same idea, PG extension) | [`pg-audit-stream-extension`](https://github.com/mizcausevic-dev/pg-audit-stream-extension) |
| The Suite this participates in | [`kinetic-gain-protocol-suite`](https://github.com/mizcausevic-dev/kinetic-gain-protocol-suite) |

## License

GPL-2.0-or-later (WordPress plugins inherit WordPress's license).
