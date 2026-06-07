<?php
/**
 * WP-CLI command.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

defined( 'ABSPATH' ) || exit;

/**
 * Manages comments from WP-CLI.
 */
final class Cli_Command {
	/**
	 * Constructor.
	 *
	 * @param Comment_Action_Service $service Comment action service.
	 */
	public function __construct( private readonly Comment_Action_Service $service ) {
	}

	/**
	 * Apply a comment management action.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : One of: trash, spam, unapprove, edit, delete.
	 *
	 * <comment-id>
	 * : The numeric comment ID.
	 *
	 * [--content=<content>]
	 * : New content. Required for the edit action.
	 *
	 * [--yes]
	 * : Skip confirmation for permanent deletion.
	 *
	 * ## EXAMPLES
	 *
	 *     wp comment-management spam 42
	 *     wp comment-management edit 42 --content="Updated comment"
	 *     wp comment-management delete 42 --yes
	 *
	 * @param string[]            $args       Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$action     = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
		$comment_id = isset( $args[1] ) ? absint( $args[1] ) : 0;

		if ( ! in_array( $action, Comment_Action_Service::ACTIONS, true ) || 0 === $comment_id ) {
			\WP_CLI::error( 'Usage: wp comment-management <trash|spam|unapprove|edit|delete> <comment-id>' );
		}

		$content = isset( $assoc_args['content'] ) ? (string) $assoc_args['content'] : null;

		if ( 'edit' === $action && null === $content ) {
			\WP_CLI::error( 'The --content option is required for the edit action.' );
		}

		if ( 'delete' === $action ) {
			\WP_CLI::confirm(
				sprintf( 'Permanently delete comment %d?', $comment_id ),
				$assoc_args
			);
		}

		$result = $this->service->execute( $comment_id, $action, $content, false );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}

		\WP_CLI::success(
			sprintf(
				'Applied "%s" to comment %d.',
				$action,
				$comment_id
			)
		);
	}
}
