=== Millaredos GDPR Comments ===
Contributors: millaredos
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YSB2YH7HHD4L2
Tags: gdpr, comments, privacy, compliance, rgpd
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.9.13.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Professional compliance framework for WordPress comments focusing on immutable traceability and data integrity.

== Description ==

Millaredos GDPR Comments provides a technical architecture to ensure EU GDPR (RGPD) compliance. It implements a privacy-by-design approach centered on accountability and data integrity.

1. **Granular Consent**: Customizable mandatory checkbox for legal acceptance.
2. **Policy Versioning**: Snapshot system that records the legal text version accepted by each user.
3. **ARCO Rights Portal**: Self-service portal using `[mgc_gdpr_dashboard]` and Magic Links for data access and anonymization.
4. **Cryptographic Integrity**: HMAC-based proofs linking comments to specific policy versions.
5. **Legible Audit Trail**: Technical logs in plain text for administrative auditing, with SHA-256 irreversible hashing for erasure events.
6. **Analytics**: Dashboard monitoring compliance rates and integrity health.

== Technical Requirements ==

* **PHP**: 8.2 or higher.
* **WordPress**: 6.5 or higher.
* **Database**: MariaDB 10.4+ or MySQL 8.0+.

== Installation ==

1. Upload to `/wp-content/plugins/millaredos-gdpr-comments`.
2. Activate the plugin.
3. Configure legal texts in Settings -> Millaredos GDPR.

== Frequently Asked Questions ==

= How is data erasure handled? =
When a user requests anonymization via the ARCO Portal, their personal data is removed from the comments table. The audit log records the event using a SHA-256 hash of the email address, ensuring zero personal data retention while maintaining legal accountability.

== Upgrade Notice ==

= 0.9.13.3 =
Complete architectural redesign of the logging and traceability system. Implements policy versioning, cryptographic proofing, and full WordPress 6.7 compatibility. Critical security and stability update.

== Changelog ==

= 0.9.13.3 =
* **Feature**: Policy Versioning System. Automatic immutable snapshots of legal texts in `mgc_policy_versions`.
* **Feature**: Visual Traceability. New modal inspection for policy snapshots and detailed logs in admin tables.
* **Feature**: Plain-text Audit Log for improved administrative transparency.
* **Security**: SHA-256 irreversible hashing for ARCO erasure/access events (Accountability without PII retention).
* **Security**: Hardened SQL identifiers using `%i` and reinforced AJAX security with immediate `wp_kses` escaping.
* **Fix**: Resolved critical Fatal Error caused by dependencies on private core functions.
* **Fix**: Full WordPress 6.7 compatibility (I18n loading notices silenced).

= 0.9.12 =
* **Fix**: Synchronized documentation and internal changelogs.

= 0.9.11 =
* **Fix**: Resolved version mismatch loop and admin menu icon layout issues.

= 0.9.9 =
* **Feature**: Automatic Update engine via GitHub Mirror.
* **Feature**: Full es_ES localization.
* **Security**: HMAC data integrity proofs.
