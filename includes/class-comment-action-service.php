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
	/**
	 * Supported action names.
	 *
	 * @var string[]
	 */
	public const ACTIONS = array( 'trash', 'spam', 'unapprove', 'edit', 'delete' );

	/**
	 * Execute a comment action.
	 *
	 * @param int         $comment_id      Comment ID.
	 * @param string      $action          Action name.
	 * @param string|null $content         New comment content for edit actions.
	 * @param bool        $check_capability Whether to check the current user's capability.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function execute(
		int $comment_id,
		string $action,
		?string $content = null,
		bool $check_capability = true
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
			$check_capability
			&& (
				! current_user_can( 'moderate_comments' )
				|| ! current_user_can( 'edit_comment', $comment_id )
			)
		) {
			return new \WP_Error(
				'comment_management_forbidden',
				__( 'You are not allowed to manage this comment.', 'comment-management' )
			);
		}

		$result = match ( $action ) {
			'trash'     => wp_trash_comment( $comment ),
			'spam'      => wp_spam_comment( $comment ),
			'unapprove' => wp_set_comment_status( $comment, 'hold', true ),
			'edit'      => $this->update_content( $comment, $content ),
			'delete'    => wp_delete_comment( $comment, true ),
		};

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error(
				'comment_management_action_failed',
				__( 'WordPress could not update the comment.', 'comment-management' )
			);
		}

		return array(
			'action'     => $action,
			'comment_id' => $comment_id,
			'removed'    => 'edit' !== $action,
			'comment'    => 'edit' === $action ? get_comment( $comment_id ) : null,
		);
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

		return wp_update_comment(
			array(
				'comment_ID'      => $comment->comment_ID,
				'comment_content' => $content,
			),
			true
		);
	}
}
