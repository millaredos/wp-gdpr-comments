<?php
/**
 * Handles the GDPR ARCO Rights Portal (Access, Rectification, Cancellation, Opposition).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Millaredos_GDPR_Comments_ARCO_Portal {

	/**
	 * Initialize the class and register the shortcode.
	 */
	public function init() {
		add_shortcode( 'mgc_gdpr_dashboard', array( $this, 'render_dashboard' ) );
		add_action( 'init', array( $this, 'handle_request' ) );
		add_action( 'template_redirect', array( $this, 'log_access_event' ) );
	}

	/**
	 * Log access request before output (Architectural Standard).
	 */
	public function log_access_event() {
		$token = isset( $_GET['mgc_token'] ) ? sanitize_text_field( wp_unslash( $_GET['mgc_token'] ) ) : '';
		if ( empty( $token ) ) {
			return;
		}

		$email = get_transient( 'mgc_arco_' . $token );
		if ( false === $email ) {
			return;
		}

		// Only log once per session visit (not on updates/anonymization redirects)
		if ( ! isset( $_GET['mgc_updated'] ) ) {
			$email_hash = MGC_Audit_Log::hash_sha256( $email );
			MGC_Audit_Log::write( array(
				'comment_id'  => 0,
				'user_id'     => 0,
				'change_type' => 'arco_access',
				'old_value'   => $email_hash,
				'new_value'   => $email_hash,
				'old_hash'    => '',
			) );
		}
	}

	/**
	 * Verify if the current request has a valid guest session token.
	 *
	 * @return bool
	 */
	private function is_valid_guest_session() {
		$token = '';
		if ( isset( $_POST['mgc_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['mgc_token'] ) );
		} elseif ( isset( $_GET['mgc_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_GET['mgc_token'] ) );
		}

		if ( empty( $token ) ) {
			return false;
		}

		return false !== get_transient( 'mgc_arco_' . $token );
	}

	/**
	 * Handle POST requests for Magic Link generation or data actions.
	 */
	public function handle_request() {
		if ( ! isset( $_POST['mgc_arco_action'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['mgc_arco_action'] ) );

		// Security: Every action triggered by a user must be verified using wp_verify_nonce() AND permissions.
		if ( 'request_link' === $action ) {
			// This is the entry point, anyone can request a link if they pass the nonce.
			$this->process_magic_link_request();
			return;
		}

		// For sensitive actions (anonymization), verify the session or capability.
		if ( ! current_user_can( 'manage_options' ) && ! $this->is_valid_guest_session() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'millaredos-gdpr-comments' ) );
		}

		if ( 'anonymize_comment' === $action ) {
			$this->process_anonymization_request();
		}
	}

	/**
	 * Render the ARCO Dashboard via shortcode.
	 */
	public function render_dashboard() {
		// Hybrid check for the shortcode: Allow guest access for GDPR rights.
		if ( ! is_user_logged_in() && ! current_user_can( 'read' ) ) {
			// Guests allowed.
		}

		$token = isset( $_GET['mgc_token'] ) ? sanitize_text_field( wp_unslash( $_GET['mgc_token'] ) ) : '';
		
		if ( empty( $token ) ) {
			return $this->render_access_form();
		}

		$email = get_transient( 'mgc_arco_' . $token );
		if ( false === $email ) {
			return '<div class="mgc-error">' . esc_html__( 'Invalid or expired token. Please request a new link.', 'millaredos-gdpr-comments' ) . '</div>' . $this->render_access_form();
		}

		return $this->render_user_data_panel( $email, $token );
	}

	/**
	 * Render the initial form to request a Magic Link.
	 */
	private function render_access_form() {
		ob_start();
		?>
		<div class="mgc-arco-container">
			<h3><?php esc_html_e( 'Manage your personal data', 'millaredos-gdpr-comments' ); ?></h3>
			<p><?php esc_html_e( 'Enter the email address you used to comment to receive a secure access link to manage your data.', 'millaredos-gdpr-comments' ); ?></p>
			
			<?php if ( isset( $_GET['mgc_sent'] ) ) : ?>
				<div class="mgc-success">
					<?php esc_html_e( 'If the email exists in our records, you will receive an access link shortly. Please check your inbox (and spam folder).', 'millaredos-gdpr-comments' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'mgc_request_link_action', 'mgc_request_link_nonce' ); ?>
				<input type="hidden" name="mgc_arco_action" value="request_link">
				<p>
					<label for="mgc_email"><?php esc_html_e( 'Your Email', 'millaredos-gdpr-comments' ); ?></label><br>
					<input type="email" name="mgc_email" id="mgc_email" required class="regular-text">
				</p>
				<p>
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Send Access Link', 'millaredos-gdpr-comments' ); ?>">
				</p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Process the Magic Link generation and send email.
	 */
	private function process_magic_link_request() {
		if ( ! isset( $_POST['mgc_request_link_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['mgc_request_link_nonce'] ), 'mgc_request_link_action' ) ) {
			return;
		}

		$email = isset( $_POST['mgc_email'] ) ? sanitize_email( wp_unslash( $_POST['mgc_email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			return;
		}

		// Check if user has comments
		global $wpdb;
		$has_comments = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE comment_author_email = %s", $wpdb->comments, $email ) );

		if ( $has_comments ) {
			$token = bin2hex( random_bytes( 32 ) );
			set_transient( 'mgc_arco_' . $token, $email, 30 * MINUTE_IN_SECONDS );

			// Get absolute permalink of the ARCO page
			$page_id    = get_option( 'mgc_arco_page_id' );
			$target_url = $page_id ? get_permalink( $page_id ) : get_home_url();
			$access_url = add_query_arg( 'mgc_token', $token, $target_url );
			
			$site_name  = get_bloginfo( 'name' );
			
			$subject = sprintf( __( 'Access to your personal data on %s', 'millaredos-gdpr-comments' ), $site_name );
			$message = sprintf( 
				__( 'Hello, you have requested access to manage your personal data in the comments of %1$s. Click the following link to access your management panel (valid for 30 minutes): %2$s', 'millaredos-gdpr-comments' ),
				$site_name,
				esc_url_raw( $access_url )
			);

			wp_mail( $email, $subject, $message );
		}

		$redirect_url = get_permalink( get_option( 'mgc_arco_page_id' ) );
		wp_safe_redirect( add_query_arg( 'mgc_sent', '1', $redirect_url ) );
		exit;
	}

	/**
	 * Render the dashboard with user comments.
	 */
	private function render_user_data_panel( $email, $token ) {
		global $wpdb;
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE comment_author_email = %s AND 'spam' != comment_approved ORDER BY comment_date DESC", $wpdb->comments, $email ) );

		ob_start();
		?>
		<div class="mgc-arco-dashboard">
			<h3><?php echo esc_html( sprintf( __( 'Data Management for %s', 'millaredos-gdpr-comments' ), $email ) ); ?></h3>
			
			<?php if ( empty( $comments ) ) : ?>
				<p><?php esc_html_e( 'No comments found associated with this email.', 'millaredos-gdpr-comments' ); ?></p>
			<?php else : ?>
				<table class="mgc-arco-table" style="width:100%; border-collapse: collapse; margin-top: 20px;">
					<thead>
						<tr style="background: #f4f4f4; border-bottom: 2px solid #ddd;">
							<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Date', 'millaredos-gdpr-comments' ); ?></th>
							<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Post', 'millaredos-gdpr-comments' ); ?></th>
							<th style="padding: 10px; text-align: left;"><?php esc_html_e( 'Comment Extract', 'millaredos-gdpr-comments' ); ?></th>
							<th style="padding: 10px; text-align: center;"><?php esc_html_e( 'Action', 'millaredos-gdpr-comments' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $comments as $comment ) : ?>
							<tr style="border-bottom: 1px solid #eee;">
								<td style="padding: 10px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $comment->comment_date ) ) ); ?></td>
								<td style="padding: 10px;"><a href="<?php echo esc_url( get_permalink( $comment->comment_post_ID ) ); ?>"><?php echo esc_html( get_the_title( $comment->comment_post_ID ) ); ?></a></td>
								<td style="padding: 10px; font-style: italic;">
									"<?php echo esc_html( wp_trim_words( $comment->comment_content, 10 ) ); ?>"
								</td>
								<td style="padding: 10px; text-align: center;">
									<form method="post" action="" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to anonymize this comment? This action cannot be undone.', 'millaredos-gdpr-comments' ); ?>');">
										<?php wp_nonce_field( 'mgc_anonymize_action_' . $comment->comment_ID, 'mgc_anonymize_nonce' ); ?>
										<input type="hidden" name="mgc_arco_action" value="anonymize_comment">
										<input type="hidden" name="comment_id" value="<?php echo esc_attr( absint( $comment->comment_ID ) ); ?>">
										<input type="hidden" name="mgc_token" value="<?php echo esc_attr( $token ); ?>">
										<input type="submit" class="button" style="color: #d63638;" value="<?php esc_attr_e( 'Anonymize', 'millaredos-gdpr-comments' ); ?>">
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Process the anonymization of a specific comment.
	 */
	private function process_anonymization_request() {
		$comment_id = isset( $_POST['comment_id'] ) ? absint( wp_unslash( $_POST['comment_id'] ) ) : 0;
		$token      = isset( $_POST['mgc_token'] ) ? sanitize_text_field( wp_unslash( $_POST['mgc_token'] ) ) : '';

		if ( ! wp_verify_nonce( wp_unslash( $_POST['mgc_anonymize_nonce'] ), 'mgc_anonymize_action_' . $comment_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'millaredos-gdpr-comments' ) );
		}

		$email = get_transient( 'mgc_arco_' . $token );
		if ( false === $email ) {
			wp_die( esc_html__( 'Session expired. Please request a new link.', 'millaredos-gdpr-comments' ) );
		}

		$comment = get_comment( $comment_id );
		if ( ! $comment || $email !== $comment->comment_author_email ) {
			wp_die( esc_html__( 'You do not have permission to manage this comment.', 'millaredos-gdpr-comments' ) );
		}

		// Perform Anonymization
		$old_author = $comment->comment_author;
		$old_email  = $comment->comment_author_email;
		$old_ip     = $comment->comment_author_IP;
		$old_hash   = get_comment_meta( $comment_id, '_mgc_consent_proof_hash', true );

		global $wpdb;
		$wpdb->update(
			$wpdb->comments,
			array(
				'comment_author'       => __( 'Anonymous', 'millaredos-gdpr-comments' ),
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_author_IP'    => wp_privacy_anonymize_ip( $old_ip ), // Respect WP standards
			),
			array( 'comment_ID' => $comment_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Log Right to Erasure with irreversible hash for Accountability
		$email_hash = MGC_Audit_Log::hash_sha256( $old_email );
		MGC_Audit_Log::write( array(
			'comment_id'  => $comment_id,
			'change_type' => 'arco_erasure',
			'user_id'     => 0,
			'old_value'   => $email_hash,
			'new_value'   => __( 'Data anonymized by user request.', 'millaredos-gdpr-comments' ),
			'old_hash'    => $old_hash, // Maintain Accountability chain
		) );

		$redirect_url = get_permalink( get_option( 'mgc_arco_page_id' ) );
		wp_safe_redirect( add_query_arg( array( 'mgc_token' => $token, 'mgc_updated' => '1' ), $redirect_url ) );
		exit;
	}
}
