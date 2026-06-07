<?php
/**
 * Uninstall handler.
 *
 * @package CommentManagement
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'comment_management_update_settings' );
delete_metadata(
	'comment',
	0,
	'_comment_management_edit_history',
	'',
	true
);
delete_metadata(
	'comment',
	0,
	'_comment_management_undo_tokens',
	'',
	true
);
