<?php
/**
 * Comment action service.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

defined( 'ABSPATH' ) || exit;

/**
 * Applies supported comment mutations through WordPress core APIs.
 */
final class Comment_Action_Service {
	private const HISTORY_META_KEY = '_comment_management_edit_history';
	private const HISTORY_LIMIT    = 20;
	private const UNDO_TTL         = 300;

	/**
	 * Supported action names.
	 *
	 * @var string[]
	 */
	public const ACTIONS = array(
		'trash',
		'spam',
		'unapprove',
		'edit',
		'delete',
		'undo',
		'history',
		'restore_revision',
	);

	/**
	 * Execute a comment action.
	 *
	 * @param int         $comment_id Comment ID.
	 * @param string      $action     Action name.
	 * @param string|null $content    New comment content for edit actions.
	 * @param string|null $reference  Undo token or revision ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute(
		int $comment_id,
		string $action,
		?string $content = null,
		?string $reference = null
	): array|\WP_Error {
		$action = sanitize_key( $action );

		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return new \WP_Error(
				'comment_management_invalid_action',
				__( 'This comment action is not supported.', 'comment-management' )
			);
		}

		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			return new \WP_Error(
				'comment_management_comment_not_found',
				__( 'The comment could not be found.', 'comment-management' )
			);
		}

		if (
			! current_user_can( 'moderate_comments' )
			|| ! current_user_can( 'edit_comment', $comment_id )
		) {
			return new \WP_Error(
				'comment_management_forbidden',
				__( 'You are not allowed to manage this comment.', 'comment-management' )
			);
		}

		if ( 'history' === $action ) {
			return array(
				'action'     => $action,
				'comment_id' => $comment_id,
				'removed'    => false,
				'history'    => $this->get_history( $comment ),
			);
		}

		if ( 'undo' === $action ) {
			return $this->undo_action( $comment, (string) $reference );
		}

		if ( 'restore_revision' === $action ) {
			$result = $this->restore_revision( $comment, (string) $reference );
		} else {
			$previous_status = (string) $comment->comment_approved;
			$result          = match ( $action ) {
				'trash'     => wp_trash_comment( $comment ),
				'spam'      => wp_spam_comment( $comment ),
				'unapprove' => wp_set_comment_status( $comment, 'hold', true ),
				'edit'      => $this->update_content( $comment, $content ),
				'delete'    => wp_delete_comment( $comment, true ),
			};
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error(
				'comment_management_action_failed',
				__( 'WordPress could not update the comment.', 'comment-management' )
			);
		}

		$response = array(
			'action'     => $action,
			'comment_id' => $comment_id,
			'removed'    => 'delete' === $action,
			'comment'    => in_array( $action, array( 'edit', 'restore_revision' ), true )
				? get_comment( $comment_id )
				: null,
		);

		if ( in_array( $action, array( 'trash', 'spam', 'unapprove' ), true ) ) {
			$response['undo_token'] = $this->create_undo_token(
				$comment,
				$action,
				$previous_status
			);
			$response['status']     = $action;
		}

		return $response;
	}

	/**
	 * Update comment content.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @param string|null $content New content.
	 * @return int|\WP_Error|false
	 */
	private function update_content( \WP_Comment $comment, ?string $content ): int|\WP_Error|false {
		$content = wp_kses_post( (string) $content );

		if ( '' === trim( $content ) ) {
			return new \WP_Error(
				'comment_management_empty_content',
				__( 'Comment content cannot be empty.', 'comment-management' )
			);
		}

		if ( $content === $comment->comment_content ) {
			return $comment->comment_ID;
		}

		$this->store_revision( $comment );

		return wp_update_comment(
			array(
				'comment_ID'      => $comment->comment_ID,
				'comment_content' => $content,
			),
			true
		);
	}

	/**
	 * Store the current comment content as a revision.
	 *
	 * @param \WP_Comment $comment Comment object.
	 */
	private function store_revision( \WP_Comment $comment ): void {
		$history   = $this->get_raw_history( $comment );
		$history[] = array(
			'id'      => wp_generate_uuid4(),
			'content' => $comment->comment_content,
			'user_id' => get_current_user_id(),
			'time'    => time(),
		);
		$history   = array_slice( $history, -self::HISTORY_LIMIT );

		update_comment_meta( $comment->comment_ID, self::HISTORY_META_KEY, $history );
	}

	/**
	 * Get history formatted for the front end.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return array<int, array{id:string,content:string,user:string,date:string}>
	 */
	private function get_history( \WP_Comment $comment ): array {
		$history = array_reverse( $this->get_raw_history( $comment ) );

		return array_map(
			static function ( array $revision ): array {
				$user = get_userdata( (int) $revision['user_id'] );

				return array(
					'id'      => (string) $revision['id'],
					'content' => (string) $revision['content'],
					'user'    => $user instanceof \WP_User
						? $user->display_name
						: __( 'Unknown user', 'comment-management' ),
					'date'    => wp_date(
						get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
						(int) $revision['time']
					),
				);
			},
			$history
		);
	}

	/**
	 * Restore one stored revision.
	 *
	 * @param \WP_Comment $comment    Comment object.
	 * @param string      $revision_id Revision identifier.
	 * @return int|\WP_Error|false
	 */
	private function restore_revision(
		\WP_Comment $comment,
		string $revision_id
	): int|\WP_Error|false {
		foreach ( $this->get_raw_history( $comment ) as $revision ) {
			if ( hash_equals( (string) $revision['id'], $revision_id ) ) {
				return $this->update_content( $comment, (string) $revision['content'] );
			}
		}

		return new \WP_Error(
			'comment_management_revision_not_found',
			__( 'The selected comment revision could not be found.', 'comment-management' )
		);
	}

	/**
	 * Normalize stored revision data.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return array<int, array{id:string,content:string,user_id:int,time:int}>
	 */
	private function get_raw_history( \WP_Comment $comment ): array {
		$history = get_comment_meta(
			$comment->comment_ID,
			self::HISTORY_META_KEY,
			true
		);

		if ( ! is_array( $history ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$history,
				static fn ( mixed $revision ): bool => (
					is_array( $revision )
					&& isset(
						$revision['id'],
						$revision['content'],
						$revision['user_id'],
						$revision['time']
					)
					&& is_string( $revision['id'] )
					&& is_string( $revision['content'] )
				)
			)
		);
	}

	/**
	 * Create a one-time undo token.
	 *
	 * @param \WP_Comment $comment         Comment object.
	 * @param string      $action          Original action.
	 * @param string      $previous_status Previous comment status.
	 * @return string
	 */
	private function create_undo_token(
		\WP_Comment $comment,
		string $action,
		string $previous_status
	): string {
		$payload = wp_json_encode(
			array(
				'user_id'         => get_current_user_id(),
				'comment_id'      => $comment->comment_ID,
				'action'          => $action,
				'previous_status' => $previous_status,
				'expires_at'      => time() + self::UNDO_TTL,
				'nonce'           => wp_generate_password( 20, false, false ),
			)
		);

		if ( ! is_string( $payload ) ) {
			return '';
		}

		// Base64url encodes signed token data for safe transport in form fields.
		$encoded   = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$signature = hash_hmac( 'sha256', $encoded, wp_salt( 'nonce' ) );

		return $encoded . '.' . $signature;
	}

	/**
	 * Undo a moderation action.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @param string      $token   One-time undo token.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function undo_action( \WP_Comment $comment, string $token ): array|\WP_Error {
		if ( '' === $token ) {
			return new \WP_Error(
				'comment_management_invalid_undo',
				__( 'The undo request is invalid or has expired.', 'comment-management' )
			);
		}

		$parts = explode( '.', $token, 2 );
		$data  = null;

		if (
			2 === count( $parts )
			&& hash_equals(
				hash_hmac( 'sha256', $parts[0], wp_salt( 'nonce' ) ),
				$parts[1]
			)
		) {
			$encoded = strtr( $parts[0], '-_', '+/' );
			$padding = strlen( $encoded ) % 4;

			if ( 0 !== $padding ) {
				$encoded .= str_repeat( '=', 4 - $padding );
			}

			$decoded = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$data    = is_string( $decoded )
				? json_decode( $decoded, true )
				: null;
		}

		if (
			! is_array( $data )
			|| ! isset(
				$data['user_id'],
				$data['comment_id'],
				$data['action'],
				$data['previous_status'],
				$data['expires_at']
			)
			|| get_current_user_id() !== (int) $data['user_id']
			|| (int) $data['comment_id'] !== $comment->comment_ID
			|| time() > (int) $data['expires_at']
			|| ! $this->has_expected_undo_status( $comment, (string) $data['action'] )
		) {
			return new \WP_Error(
				'comment_management_invalid_undo',
				__( 'The undo request is invalid or has expired.', 'comment-management' )
			);
		}

		$result = match ( $data['action'] ) {
			'trash'     => wp_untrash_comment( $comment ),
			'spam'      => wp_unspam_comment( $comment ),
			'unapprove' => wp_set_comment_status(
				$comment,
				'1' === (string) $data['previous_status'] ? 'approve' : 'hold',
				true
			),
			default     => false,
		};

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error(
				'comment_management_undo_failed',
				__( 'WordPress could not undo the comment action.', 'comment-management' )
			);
		}

		return array(
			'action'     => 'undo',
			'comment_id' => $comment->comment_ID,
			'removed'    => false,
			'status'     => (string) $data['previous_status'],
		);
	}

	/**
	 * Check that the original moderation action is still in effect.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @param string      $action  Original action.
	 * @return bool
	 */
	private function has_expected_undo_status( \WP_Comment $comment, string $action ): bool {
		$status = (string) $comment->comment_approved;

		return match ( $action ) {
			'trash'     => 'trash' === $status,
			'spam'      => 'spam' === $status,
			'unapprove' => in_array( $status, array( '0', 'hold' ), true ),
			default     => false,
		};
	}
}
