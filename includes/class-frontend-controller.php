<?php
/**
 * Front-end comment management controller.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

defined( 'ABSPATH' ) || exit;

/**
 * Renders comment controls and handles authenticated AJAX requests.
 */
final class Frontend_Controller {
	private const AJAX_ACTION  = 'comment_management_apply_action';
	private const NONCE_ACTION = 'comment_management_manage_comment';

	/**
	 * Whether comment text is being rendered for an AJAX response.
	 *
	 * @var bool
	 */
	private bool $rendering_response = false;

	/**
	 * Constructor.
	 *
	 * @param Comment_Action_Service $service Comment action service.
	 */
	public function __construct( private readonly Comment_Action_Service $service ) {
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'comment_text', array( $this, 'append_controls' ), 99, 3 );
		add_filter( 'wpdiscuz_comment_end', array( $this, 'append_wpdiscuz_controls' ), 99, 4 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Enqueue the front-end assets for users who can moderate comments.
	 */
	public function enqueue_assets(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'moderate_comments' ) ) {
			return;
		}

		wp_enqueue_style(
			'comment-management',
			COMMENT_MANAGEMENT_URL . 'assets/css/comment-management.css',
			array(),
			COMMENT_MANAGEMENT_VERSION
		);

		wp_enqueue_script(
			'comment-management',
			COMMENT_MANAGEMENT_URL . 'assets/js/comment-management.js',
			array(),
			COMMENT_MANAGEMENT_VERSION,
			true
		);

		wp_localize_script(
			'comment-management',
			'CommentManagement',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'strings' => array(
					'confirmDelete' => __( 'Permanently delete this comment? This cannot be undone.', 'comment-management' ),
					'historyEmpty'  => __( 'No edit history is available.', 'comment-management' ),
					'historyTitle'  => __( 'Comment edit history', 'comment-management' ),
					'requestFailed' => __( 'The request failed. Please try again.', 'comment-management' ),
					'restore'       => __( 'Restore', 'comment-management' ),
					'saving'        => __( 'Saving...', 'comment-management' ),
					'working'       => __( 'Working...', 'comment-management' ),
					'undo'          => __( 'Undo', 'comment-management' ),
					'undoExpired'   => __( 'The undo period has ended.', 'comment-management' ),
				),
			)
		);
	}

	/**
	 * Add controls to comment text.
	 *
	 * This filter is used by the default walker and by wpDiscuz-compatible
	 * rendering paths that apply WordPress's standard comment_text filter.
	 *
	 * @param string $text    Filtered comment text.
	 * @param mixed  $comment Comment object.
	 * @param mixed  $args    Comment display arguments.
	 * @return string
	 */
	public function append_controls( string $text, mixed $comment = null, mixed $args = array() ): string {
		if (
			$this->rendering_response
			|| ( is_admin() && ! wp_doing_ajax() )
			|| ! is_user_logged_in()
			|| ! current_user_can( 'moderate_comments' )
			|| str_contains( $text, 'data-comment-management-controls' )
		) {
			return $text;
		}

		$comment = $comment instanceof \WP_Comment ? $comment : get_comment();

		if (
			! $comment instanceof \WP_Comment
			|| ! current_user_can( 'edit_comment', $comment->comment_ID )
		) {
			return $text;
		}

		$renderer = $this->is_wpdiscuz_context( $args ) ? 'wpdiscuz' : 'default';

		return sprintf(
			'<div class="cm-managed-comment"><div class="cm-managed-content">%1$s</div>%2$s</div>',
			$text,
			$this->render_controls( $comment, $renderer )
		);
	}

	/**
	 * Add controls after a wpDiscuz comment without polluting comment content.
	 *
	 * @param string $output  Rendered wpDiscuz comment.
	 * @param mixed  $comment Comment object.
	 * @param mixed  $depth   Comment depth.
	 * @param mixed  $args    wpDiscuz walker arguments.
	 * @return string
	 */
	public function append_wpdiscuz_controls(
		string $output,
		mixed $comment = null,
		mixed $depth = null,
		mixed $args = array()
	): string {
		unset( $depth, $args );

		if (
			( is_admin() && ! wp_doing_ajax() )
			|| ! is_user_logged_in()
			|| ! current_user_can( 'moderate_comments' )
			|| str_contains( $output, 'data-comment-management-controls' )
			|| ! $comment instanceof \WP_Comment
			|| ! current_user_can( 'edit_comment', $comment->comment_ID )
		) {
			return $output;
		}

		return $output . $this->render_controls( $comment, 'wpdiscuz' );
	}

	/**
	 * Handle a front-end comment action.
	 */
	public function handle_ajax(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => __( 'You must be logged in.', 'comment-management' ) ),
				401
			);
		}

		if ( false === check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'The security token is invalid or has expired.', 'comment-management' ) ),
				403
			);
		}

		$comment_id   = isset( $_POST['commentId'] ) && is_scalar( $_POST['commentId'] )
			? absint( $_POST['commentId'] )
			: 0;
		$operation    = isset( $_POST['operation'] ) && is_string( $_POST['operation'] )
			? sanitize_key( wp_unslash( $_POST['operation'] ) )
			: '';
		$content      = isset( $_POST['content'] ) && is_string( $_POST['content'] )
			? wp_kses_post( wp_unslash( $_POST['content'] ) )
			: null;
		$confirmation = isset( $_POST['confirmation'] ) && is_string( $_POST['confirmation'] )
			? sanitize_text_field( wp_unslash( $_POST['confirmation'] ) )
			: '';
		$renderer     = isset( $_POST['renderer'] ) && is_string( $_POST['renderer'] )
			? sanitize_key( wp_unslash( $_POST['renderer'] ) )
			: 'default';
		$reference    = isset( $_POST['reference'] ) && is_string( $_POST['reference'] )
			? preg_replace(
				'/[^A-Za-z0-9_.-]/',
				'',
				sanitize_text_field( wp_unslash( $_POST['reference'] ) )
			)
			: null;

		if (
			'delete' === $operation
			&& 'DELETE' !== $confirmation
		) {
			wp_send_json_error(
				array( 'message' => __( 'Permanent deletion was not confirmed.', 'comment-management' ) ),
				400
			);
		}

		$result = $this->service->execute(
			$comment_id,
			$operation,
			$content,
			$reference
		);

		if ( is_wp_error( $result ) ) {
			$status = 'comment_management_forbidden' === $result->get_error_code() ? 403 : 400;
			wp_send_json_error( array( 'message' => $result->get_error_message() ), $status );
		}

		$response = array(
			'action'      => $result['action'],
			'commentId'   => $result['comment_id'],
			'removed'     => $result['removed'],
			'message'     => $this->get_success_message( $result['action'] ),
			'contentHtml' => '',
			'contentRaw'  => '',
			'history'     => $result['history'] ?? array(),
			'status'      => $result['status'] ?? '',
			'statusLabel' => isset( $result['status'] )
				? $this->get_status_label( (string) $result['status'] )
				: '',
			'undoToken'   => $result['undo_token'] ?? '',
		);

		if (
			in_array( $result['action'], array( 'edit', 'restore_revision' ), true )
			&& $result['comment'] instanceof \WP_Comment
		) {
			$this->rendering_response = true;

			try {
				$content_html = apply_filters(
					'comment_text',
					$result['comment']->comment_content,
					$result['comment'],
					'wpdiscuz' === $renderer
						? array( 'is_wpdiscuz_comment' => true )
						: array()
				);
			} finally {
				$this->rendering_response = false;
			}

			$response['contentHtml'] = wp_kses_post( (string) $content_html );
			$response['contentRaw']  = $result['comment']->comment_content;
		}

		wp_send_json_success( $response );
	}

	/**
	 * Render controls for one comment.
	 *
	 * @param \WP_Comment $comment  Comment object.
	 * @param string      $renderer Renderer identifier.
	 * @return string
	 */
	private function render_controls( \WP_Comment $comment, string $renderer ): string {
		$comment_id   = (int) $comment->comment_ID;
		$status       = (string) $comment->comment_approved;
		$status_label = $this->get_status_label( $status );

		return sprintf(
			'<div class="cm-controls" data-comment-management-controls data-comment-id="%1$d" data-content="%2$s" data-renderer="%3$s">
				<div class="cm-controls-header">
					<span class="cm-status-badge" data-comment-status="%4$s"%5$s>%6$s</span>
					<div class="cm-menu">
						<button type="button" class="cm-menu-toggle" aria-expanded="false" aria-haspopup="menu" aria-controls="cm-menu-%1$d">
							<span aria-hidden="true">&#8942;</span>
							<span class="screen-reader-text">%8$s</span>
						</button>
						<div id="cm-menu-%1$d" class="cm-actions" role="menu" aria-label="%7$s" hidden>
							<button type="button" class="cm-action" role="menuitem" data-operation="edit">%9$s</button>
							<button type="button" class="cm-action" role="menuitem" data-operation="history">%10$s</button>
							<button type="button" class="cm-action" role="menuitem" data-operation="unapprove">%11$s</button>
							<button type="button" class="cm-action" role="menuitem" data-operation="spam">%12$s</button>
							<button type="button" class="cm-action" role="menuitem" data-operation="trash">%13$s</button>
							<button type="button" class="cm-action cm-action-danger" role="menuitem" data-operation="delete">%14$s</button>
						</div>
					</div>
				</div>
				<div class="cm-editor" hidden>
					<label class="screen-reader-text" for="cm-content-%1$d">%15$s</label>
					<textarea id="cm-content-%1$d" class="cm-editor-content" rows="5"></textarea>
					<div class="cm-editor-actions">
						<button type="button" class="cm-save">%16$s</button>
						<button type="button" class="cm-cancel">%17$s</button>
					</div>
				</div>
				<div class="cm-history" hidden></div>
				<p class="cm-status" role="status" aria-live="polite"></p>
			</div>',
			$comment_id,
			esc_attr( $comment->comment_content ),
			esc_attr( $renderer ),
			esc_attr( $status ),
			'' === $status_label ? ' hidden' : '',
			esc_html( $status_label ),
			esc_attr__( 'Comment management actions', 'comment-management' ),
			esc_html__( 'More comment actions', 'comment-management' ),
			esc_html__( 'Edit', 'comment-management' ),
			esc_html__( 'History', 'comment-management' ),
			esc_html__( 'Unapprove', 'comment-management' ),
			esc_html__( 'Mark as spam', 'comment-management' ),
			esc_html__( 'Move to Trash', 'comment-management' ),
			esc_html__( 'Delete permanently', 'comment-management' ),
			esc_html__( 'Comment content', 'comment-management' ),
			esc_html__( 'Save', 'comment-management' ),
			esc_html__( 'Cancel', 'comment-management' )
		);
	}

	/**
	 * Determine whether comment_text is running inside wpDiscuz.
	 *
	 * @param mixed $args Filter arguments.
	 * @return bool
	 */
	private function is_wpdiscuz_context( mixed $args ): bool {
		return is_array( $args )
			&& (
				! empty( $args['is_wpdiscuz_comment'] )
				|| isset( $args['components'] )
			);
	}

	/**
	 * Get an action success message.
	 *
	 * @param string $action Action name.
	 * @return string
	 */
	private function get_success_message( string $action ): string {
		return match ( $action ) {
			'trash'            => __( 'The comment was moved to the Trash.', 'comment-management' ),
			'spam'             => __( 'The comment was marked as spam.', 'comment-management' ),
			'unapprove'        => __( 'The comment was unapproved.', 'comment-management' ),
			'edit'             => __( 'The comment was updated.', 'comment-management' ),
			'delete'           => __( 'The comment was permanently deleted.', 'comment-management' ),
			'history'          => '',
			'restore_revision' => __( 'The comment revision was restored.', 'comment-management' ),
			'undo'             => __( 'The comment action was undone.', 'comment-management' ),
			default            => __( 'The comment was updated.', 'comment-management' ),
		};
	}

	/**
	 * Get a human-readable comment status.
	 *
	 * @param string $status Comment status.
	 * @return string
	 */
	private function get_status_label( string $status ): string {
		return match ( $status ) {
			'hold', 'unapprove' => __( 'Pending', 'comment-management' ),
			'spam'              => __( 'Spam', 'comment-management' ),
			'trash'             => __( 'Trash', 'comment-management' ),
			default             => '',
		};
	}
}
