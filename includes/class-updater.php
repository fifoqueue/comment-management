<?php
/**
 * Plugin Update Checker integration.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Registers GitHub release updates.
 */
final class Updater {
	/**
	 * Register the update checker.
	 */
	public function register(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_dependency_notice' ) );
			return;
		}

		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/' . COMMENT_MANAGEMENT_GITHUB_REPOSITORY . '/',
			COMMENT_MANAGEMENT_FILE,
			'comment-management'
		);

		$update_checker->getVcsApi()->enableReleaseAssets(
			'/comment-management\.zip(?:$|[?&#])/i'
		);
	}

	/**
	 * Explain that Composer dependencies are absent.
	 */
	public function render_missing_dependency_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Comment Management is missing its Composer dependencies. Install a release package or run composer install.',
				'comment-management'
			)
		);
	}
}
