<?php
/**
 * Plugin Name:       Comment Management
 * Plugin URI:        https://github.com/fifoqueue/comment-management
 * Description:       Securely manage WordPress comments from the front end.
 * Version:           1.1.5
 * Requires at least: 6.5
 * Requires PHP:      8.3
 * Author:            FiLo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       comment-management
 * Domain Path:       /languages
 * Update URI:        https://github.com/fifoqueue/comment-management
 *
 * @package CommentManagement
 */

defined( 'ABSPATH' ) || exit;

define( 'COMMENT_MANAGEMENT_VERSION', '1.1.5' );
define( 'COMMENT_MANAGEMENT_FILE', __FILE__ );
define( 'COMMENT_MANAGEMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMMENT_MANAGEMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMENT_MANAGEMENT_GITHUB_REPOSITORY', 'fifoqueue/comment-management' );

$comment_management_autoloader = COMMENT_MANAGEMENT_PATH . 'vendor/autoload.php';

if ( is_readable( $comment_management_autoloader ) ) {
	require_once $comment_management_autoloader;
}

require_once COMMENT_MANAGEMENT_PATH . 'includes/class-comment-action-service.php';
require_once COMMENT_MANAGEMENT_PATH . 'includes/class-frontend-controller.php';
require_once COMMENT_MANAGEMENT_PATH . 'includes/class-updater.php';

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain(
			'comment-management',
			false,
			dirname( plugin_basename( COMMENT_MANAGEMENT_FILE ) ) . '/languages'
		);

		$service = new \FiLo\CommentManagement\Comment_Action_Service();

		( new \FiLo\CommentManagement\Frontend_Controller( $service ) )->register();
		( new \FiLo\CommentManagement\Updater() )->register();
	}
);
