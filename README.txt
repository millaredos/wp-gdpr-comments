=== Millaredos GDPR Comments ===
Contributors: millaredos
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=YSB2YH7HHD4L2
Tags: gdpr, comments, privacy, compliance, rgpd
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 8.2
Stable tag: 0.9.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A professional, lightweight plugin to ensure full GDPR/RGPD compliance for WordPress blog comments.

== Description ==

This plugin provides professional tools to help you comply with EU GDPR (RGPD) regulations for blog comments. It is engineered with privacy-by-design principles to offer a seamless experience for both admins and users.

1. **Mandatory Consent**: Adds a customizable "I accept the Privacy Policy" checkbox to the comment form.
2. **Transparent Information**: Displays a "First Layer" legal information table (Responsible, Purpose, Legitimation, Recipients, Rights).
3. **ARCO Rights Portal**: A secure, self-service portal via shortcode (`[mgc_gdpr_dashboard]`) for users to manage, access, and anonymize their own data using secure Magic Links.
4. **Data Integrity Proofs**: Stores cryptographic evidence of consent (HMAC) linking each comment to the legal text version accepted at the time.
5. **Comprehensive Audit Trail**: Detailed logs of all consent-related events and comment modifications to ensure total accountability.
6. **Stats Dashboard**: Visualize compliance rates, monthly trends, and integrity health at a glance.
7. **Automated Setup**: Automatically creates the necessary GDPR compliance pages upon activation.

== Technical Requirements ==

* **PHP**: 8.2 or higher.
* **WordPress**: 6.5 or higher.
* **Database**: MariaDB (100% compatibility recommended).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/millaredos-gdpr-comments` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your legal information in Settings -> Millaredos GDPR.
4. The plugin automatically creates a "GDPR Rights" page. You can also manually place the `[mgc_gdpr_dashboard]` shortcode.

== Frequently Asked Questions ==

= Does it store user IP? =
It stores an anonymized version of the IP (e.g. 192.168.1.0) along with the consent evidence to ensure privacy minimization while maintaining origin accountability.

= Is it compatible with all themes? =
Yes. It uses standard WordPress hooks and should be compatible with any well-coded theme.

== Upgrade Notice ==

= 0.9.9 =
Consolidated release. Improved stability, lowered PHP requirements to 8.2, and added a custom automatic update engine from GitHub.

== Changelog ==

= 0.9.9 =
* **Feature**: Added custom Automatic Update engine. Users can now update the plugin directly from the WordPress dashboard even when hosted on GitHub.
* **Feature**: Full Spanish (es_ES) localization for the ARCO Rights Portal and Magic Link emails.
* **Security**: Hardened data integrity proofs and audit log structures.
* **Compatibility**: Confirmed compatibility and lowered requirements to PHP 8.2.
* **Fix**: Resolved visibility issues with the PayPal donation button across all admin tabs.

= 0.9.2 =
* Bugfix: Fixed relative URLs in Magic Link emails to ensure absolute, functional links.
* Enhancement: The ARCO Portal page is now created automatically upon update or activation.

= 0.9.0 =
* Initial Public Release of the ARCO Rights Portal. Users can now request secure access to manage and anonymize their data.
