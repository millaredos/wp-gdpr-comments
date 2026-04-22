<?php

/**
 * Handles the public facing functionality.
 */
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Millaredos_GDPR_Comments_Public {

	/**
	 * Initialize the class and set its properties.
	 */
	/**
	 * Temporary storage for old comment data during update.
	 */
	private $old_comment_data = null;

	/**
	 * Initialize the class and set its properties.
	 */
	public function init(  ) {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_scripts' ) );
		add_action( 'comment_form_after_fields', array( $this, 'render_compliance_fields' ) );
		add_action( 'comment_form_logged_in_after', array( $this, 'render_compliance_fields' ) ); // Also show for logged in users
		add_filter( 'preprocess_comment', array( $this, 'validate_gdpr_consent' ) );
		add_action( 'comment_post', array( $this, 'save_consent_meta' ) );

		// Integrity Check Hooks
		add_filter( 'wp_update_comment_data', array( $this, 'capture_old_data_before_update' ), 10, 3 );
		add_action( 'edit_comment', array( $this, 'log_integrity_breach' ), 10, 2 );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_styles_scripts(  ) {
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		// Load assets only if open comments or is singular
		if ( is_singular() || is_page() || is_single() ) {
			wp_enqueue_style( 'mgc-compliance-css', MGC_URL . 'assets/css/millaredos-gdpr-comments.css', array(), MGC_VERSION, 'all' );
			wp_enqueue_script( 'mgc-compliance-js', MGC_URL . 'assets/js/millaredos-gdpr-comments.js', array(), MGC_VERSION, true );

			// Localize Script for i18n
			wp_localize_script( 'mgc-compliance-js', 'mgc_vars', array(
				'alert_msg' => __( 'Please, accept the Privacy Policy to continue.', 'millaredos-gdpr-comments' ),
			) );
		}
	}

	/**
	 * Render the checkbox and legal info.
	 */
	public function render_compliance_fields(  ) {
		$responsible  = get_option( 'mgc_responsible', __( 'Responsible not defined', 'millaredos-gdpr-comments' ) );
		$purpose      = get_option( 'mgc_purpose', __( 'Manage comments', 'millaredos-gdpr-comments' ) );
		$legitimation = get_option( 'mgc_legitimation', __( 'Consent of the interested party', 'millaredos-gdpr-comments' ) );

		// Recipients Logic
		$hosting = get_option( 'mgc_hosting_provider', __( 'a service provider', 'millaredos-gdpr-comments' ) );
		// Translators: %s is the hosting provider name
		$default_rec = sprintf( __( 'No data is ceded or communicated to third parties to provide this service. The Owner has contracted the web hosting services to %s who acts as a data processor.', 'millaredos-gdpr-comments' ), $hosting );
		$recipients  = get_option( 'mgc_recipients' );
		if ( empty( $recipients ) ) {
			$recipients = $default_rec;
		}

		$rights      = get_option( 'mgc_rights', __( 'Access, rectify and delete data', 'millaredos-gdpr-comments' ) );
		$policy_link = get_option( 'mgc_policy_link', '#' );
		$add_info    = get_option( 'mgc_additional_info', '' );

		// Create nonce
		$nonce_field = wp_nonce_field( 'mgc_comment_compliance_action', 'mgc_comment_compliance_nonce', true, false );

		?>
		<div class="mgc-compliance-wrapper">
			<p class="mgc-checkbox-row">
				<input type="checkbox" name="mgc_privacy_consent" id="mgc_privacy_consent" value="yes" required>
				<label for="mgc_privacy_consent">
					<?php
					$allowed_tags = array(
						'a' => array(
							'href'   => array(),
							'target' => array(),
						),
					);
					echo wp_kses(
						sprintf(
							/* translators: %s: privacy policy link */
							__( 'I have read and accept the <a href="%s" target="_blank">Privacy Policy</a>.', 'millaredos-gdpr-comments' ),
							esc_url( $policy_link )
						),
						$allowed_tags
					);
					?>
				</label>
			</p>
			<?php
			echo wp_kses(
				$nonce_field,
				array(
					'input' => array(
						'type'  => array(),
						'name'  => array(),
						'id'    => array(),
						'value' => array(),
					),
				)
			);
			?>

			<div class="mgc-legal-info">
				<p class="mgc-legal-toggle">
					<?php esc_html_e( 'Basic information on data protection', 'millaredos-gdpr-comments' ); ?> <span
						class="dashicons dashicons-arrow-down-alt2"></span>
				</p>
				<table class="mgc-legal-table">
					<tr>
						<th><?php esc_html_e( 'Responsible', 'millaredos-gdpr-comments' ); ?></th>
						<td><?php echo esc_html( $responsible ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Purpose', 'millaredos-gdpr-comments' ); ?></th>
						<td><?php echo esc_html( $purpose ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Legitimation', 'millaredos-gdpr-comments' ); ?></th>
						<td><?php echo esc_html( $legitimation ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Recipients', 'millaredos-gdpr-comments' ); ?></th>
						<td><?php echo esc_html( $recipients ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rights', 'millaredos-gdpr-comments' ); ?></th>
						<td><?php echo esc_html( $rights ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Additional Info', 'millaredos-gdpr-comments' ); ?></th>
						<td>
							<a href="<?php echo esc_url( $policy_link ); ?>"
								target="_blank"><?php esc_html_e( 'Privacy Policy', 'millaredos-gdpr-comments' ); ?></a>
							<?php if ( ! empty( $add_info ) ) : ?>
								| <a href="<?php echo esc_url( $add_info ); ?>"
									target="_blank"><?php esc_html_e( 'More information', 'millaredos-gdpr-comments' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Verify consent and nonce server-side.
	 */
	public function validate_gdpr_consent(  $commentdata  ) {
		// Verify nonce
		if ( ! isset( $_POST['mgc_comment_compliance_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mgc_comment_compliance_nonce'] ), 'mgc_comment_compliance_action' ) ) {
			wp_die(
				__( 'Security error: Invalid Nonce. Please reload the page.', 'millaredos-gdpr-comments' ),
				__( 'Error', 'millaredos-gdpr-comments' ),
				array( 'response' => 403, 'back_link' => true )
			);
		}

		// Verify Checkbox
		$consent = isset( $_POST['mgc_privacy_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['mgc_privacy_consent'] ) ) : '';
		if ( 'yes' !== $consent ) {
			wp_die(
				__( 'You must accept the Privacy Policy to publish a comment.', 'millaredos-gdpr-comments' ),
				__( 'Validation Error', 'millaredos-gdpr-comments' ),
				array( 'response' => 200, 'back_link' => true )
			);
		}

		return $commentdata;
	}

	/**
	 * Save consent metadata with cryptographic integrity proof.
	 */
	public function save_consent_meta(  $comment_id  ) {
		$consent = isset( $_POST['mgc_privacy_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['mgc_privacy_consent'] ) ) : '';
		if ( 'yes' === $consent ) {

			// 1. Get Basic Data
			$timestamp   = current_time( 'mysql' );
			$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
			$ip_anon     = $this->anonymize_ip( $remote_addr );
			$comment     = get_comment( $comment_id );
			$email       = $comment ? $comment->comment_author_email : '';

			// 2. Get Active Policy Snapshot ID
			$policy_id = get_option( 'mgc_active_policy_id', 0 );

			// 3. Generate Integrity Proof (HMAC)
			$secret         = wp_salt( 'nonce' );
			$data_to_sign   = $comment_id . '|' . $email . '|' . $ip_anon . '|' . $timestamp . '|' . $policy_id;
			$integrity_hash = hash_hmac( 'sha256', $data_to_sign, $secret );

			// 4. Save Metadata
			add_comment_meta( $comment_id, '_mgc_privacy_accepted', 'yes' );
			add_comment_meta( $comment_id, '_mgc_privacy_timestamp', $timestamp );
			add_comment_meta( $comment_id, '_mgc_privacy_ip_anon', $ip_anon );
			add_comment_meta( $comment_id, '_mgc_policy_version_id', $policy_id );
			add_comment_meta( $comment_id, '_mgc_consent_proof_hash', $integrity_hash ); // Proof of integrity

			// 5. Log Event (Accountability)
			$this->log_audit_event(
				$comment_id,
				'consent_given',
				'',
				sprintf(
					/* translators: %d: policy version id */
					__( 'Consent given for Privacy Policy (Version ID: %d).', 'millaredos-gdpr-comments' ),
					absint( $policy_id )
				)
			);
		}
	}

	/**
	 * Capture old comment data before it is updated in DB.
	 * 
	 * @param array $data New data.
	 * @param array $comment Old data (from DB).
	 * @param array $commentarr Original arguments.
	 */
	public function capture_old_data_before_update(  $data, $comment, $commentarr  ) {
		// Only if it's an existing comment (update)
		if ( isset( $comment['comment_ID'] ) ) {
			$this->old_comment_data = $comment;
		}
		return $data;
	}

	/**
	 * Log integrity breach when a comment is edited.
	 */
	public function log_integrity_breach(  $comment_id, $data  ) {
		// 1. Check if we have old data captured
		if ( ! $this->old_comment_data || $comment_id !== (int) $this->old_comment_data['comment_ID'] ) {
			return;
		}

		// 2. Check if it was a GDPR comment
		$is_gdpr = get_comment_meta( $comment_id, '_mgc_privacy_accepted', true );
		if ( 'yes' !== $is_gdpr ) {
			return;
		}

		// 3. Compare fields
		$old = $this->old_comment_data;
		$new = (array) $data; // Ensure array

		$changes = array();

		// Check Content
		if ( $new['comment_content'] !== $old['comment_content'] ) {
			$changes[] = array(
				'type' => 'content',
				'old'  => $old['comment_content'],
				'new'  => $new['comment_content'],
			);
		}
		// Check Author
		if ( $new['comment_author'] !== $old['comment_author'] ) {
			$changes[] = array(
				'type' => 'author',
				'old'  => $old['comment_author'],
				'new'  => $new['comment_author'],
			);
		}
		// Check Email
		if ( $new['comment_author_email'] !== $old['comment_author_email'] ) {
			$changes[] = array(
				'type' => 'email',
				'old'  => $old['comment_author_email'],
				'new'  => $new['comment_author_email'],
			);
		}

		// 4. Log Changes to DB
		if ( ! empty( $changes ) ) {
			$is_admin     = current_user_can( 'moderate_comments' );
			$event_prefix = $is_admin ? 'admin_edit_' : 'user_edit_';

			foreach ( $changes as $change ) {
				$this->log_audit_event(
					$comment_id,
					$event_prefix . $change['type'],
					$change['old'],
					$change['new']
				);
			}

			// Add a meta flag to easily find modified comments without joining
			if ( $is_admin ) {
				update_comment_meta( $comment_id, '_mgc_integrity_compromised', 'yes' );
			}
		}

		// Clean up
		$this->old_comment_data = null;
	}

	public function log_audit_event(  $comment_id, $type, $old_value = '', $new_value = ''  ) {
		MGC_Audit_Log::write_simple(
			$comment_id,
			$type,
			$old_value,
			$new_value
		);
	}

	private function anonymize_ip(  $ip  ) {
		// Use WordPress native function for IPv4 and IPv6 anonymization
		return wp_privacy_anonymize_ip( $ip );
	}
}
