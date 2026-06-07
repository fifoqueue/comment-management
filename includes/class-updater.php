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
	private const OPTION_NAME = 'comment_management_update_settings';

	/**
	 * Default update settings.
	 *
	 * @return array{repository:string, branch:string, token:string}
	 */
	public static function get_default_settings(): array {
		return array(
			'repository' => COMMENT_MANAGEMENT_GITHUB_REPOSITORY,
			'branch'     => 'master',
			'token'      => '',
		);
	}

	/**
	 * Register the update checker.
	 */
	public function register(): void {
		$this->ensure_settings_option();

		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( ! class_exists( PucFactory::class ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_dependency_notice' ) );
			return;
		}

		$settings = $this->get_settings();

		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/' . $settings['repository'] . '/',
			COMMENT_MANAGEMENT_FILE,
			'comment-management'
		);

		$update_checker->setBranch( $settings['branch'] );

		if ( '' !== $settings['token'] ) {
			$update_checker->setAuthentication( $settings['token'] );
		}

		$update_checker->getVcsApi()->enableReleaseAssets(
			'/comment-management\.zip(?:$|[?&#])/i'
		);
	}

	/**
	 * Register the updater settings page.
	 */
	public function register_settings_page(): void {
		add_options_page(
			__( 'Comment Management Updates', 'comment-management' ),
			__( 'Comment Management', 'comment-management' ),
			'manage_options',
			'comment-management-updates',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register updater settings and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'comment_management_updates',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => self::get_default_settings(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'comment_management_update_source',
			__( 'GitHub update source', 'comment-management' ),
			array( $this, 'render_settings_section' ),
			'comment-management-updates'
		);

		add_settings_field(
			'comment_management_repository',
			__( 'GitHub repository', 'comment-management' ),
			array( $this, 'render_repository_field' ),
			'comment-management-updates',
			'comment_management_update_source'
		);

		add_settings_field(
			'comment_management_branch',
			__( 'Stable branch', 'comment-management' ),
			array( $this, 'render_branch_field' ),
			'comment-management-updates',
			'comment_management_update_source'
		);

		add_settings_field(
			'comment_management_token',
			__( 'GitHub API token', 'comment-management' ),
			array( $this, 'render_token_field' ),
			'comment-management-updates',
			'comment_management_update_source'
		);
	}

	/**
	 * Sanitize updater settings.
	 *
	 * A blank token keeps the existing token so that the secret never needs to
	 * be rendered back into the settings form.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array{repository:string, branch:string, token:string}
	 */
	public function sanitize_settings( mixed $input ): array {
		$current = $this->get_settings();
		$input   = is_array( $input ) ? $input : array();

		$repository = isset( $input['repository'] ) && is_string( $input['repository'] )
			? trim( wp_unslash( $input['repository'] ) )
			: '';

		if ( ! preg_match( '/\A[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+\z/', $repository ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'invalid_repository',
				__( 'Enter the repository as owner/name.', 'comment-management' )
			);
			$repository = $current['repository'];
		}

		$branch = isset( $input['branch'] ) && is_string( $input['branch'] )
			? trim( wp_unslash( $input['branch'] ) )
			: '';

		if (
			'' === $branch
			|| strlen( $branch ) > 255
			|| ! preg_match( '/\A[A-Za-z0-9._\/-]+\z/', $branch )
			|| str_contains( $branch, '..' )
			|| str_starts_with( $branch, '/' )
			|| str_ends_with( $branch, '/' )
		) {
			add_settings_error(
				self::OPTION_NAME,
				'invalid_branch',
				__( 'Enter a valid Git branch name.', 'comment-management' )
			);
			$branch = $current['branch'];
		}

		$token = $current['token'];

		if ( ! empty( $input['clear_token'] ) ) {
			$token = '';
		} elseif ( isset( $input['token'] ) && is_string( $input['token'] ) ) {
			$submitted_token = trim( wp_unslash( $input['token'] ) );

			if ( '' !== $submitted_token ) {
				if (
					strlen( $submitted_token ) > 255
					|| preg_match( '/[\x00-\x20\x7F]/', $submitted_token )
				) {
					add_settings_error(
						self::OPTION_NAME,
						'invalid_token',
						__( 'The GitHub API token contains invalid characters.', 'comment-management' )
					);
				} else {
					$token = $submitted_token;
				}
			}
		}

		return array(
			'repository' => $repository,
			'branch'     => $branch,
			'token'      => $token,
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Comment Management Updates', 'comment-management' ); ?></h1>
			<?php settings_errors( self::OPTION_NAME ); ?>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'comment_management_updates' );
				do_settings_sections( 'comment-management-updates' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings section description.
	 */
	public function render_settings_section(): void {
		printf(
			'<p>%s</p>',
			esc_html__(
				'Plugin Update Checker uses this repository, branch, and optional token when checking GitHub for updates.',
				'comment-management'
			)
		);
	}

	/**
	 * Render the repository field.
	 */
	public function render_repository_field(): void {
		$settings = $this->get_settings();

		printf(
			'<input type="text" class="regular-text code" name="%1$s[repository]" value="%2$s" autocomplete="off" required>
			<p class="description">%3$s</p>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['repository'] ),
			esc_html__( 'Format: owner/repository. Default: fifoqueue/comment-management.', 'comment-management' )
		);
	}

	/**
	 * Render the branch field.
	 */
	public function render_branch_field(): void {
		$settings = $this->get_settings();

		printf(
			'<input type="text" class="regular-text code" name="%1$s[branch]" value="%2$s" autocomplete="off" required>
			<p class="description">%3$s</p>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['branch'] ),
			esc_html__( 'The production branch used when no newer GitHub Release is available.', 'comment-management' )
		);
	}

	/**
	 * Render the GitHub token field.
	 */
	public function render_token_field(): void {
		$settings  = $this->get_settings();
		$has_token = '' !== $settings['token'];

		printf(
			'<input type="password" class="regular-text" name="%1$s[token]" value="" autocomplete="new-password">
			<p class="description">%2$s</p>',
			esc_attr( self::OPTION_NAME ),
			esc_html__(
				'Optional. Use a fine-grained read-only token for private repositories or higher GitHub API limits. Leave blank to keep the saved token.',
				'comment-management'
			)
		);

		if ( $has_token ) {
			printf(
				'<label><input type="checkbox" name="%1$s[clear_token]" value="1"> %2$s</label>',
				esc_attr( self::OPTION_NAME ),
				esc_html__( 'Remove the saved token', 'comment-management' )
			);
		}
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

	/**
	 * Ensure the option is not added to WordPress's autoloaded option cache.
	 */
	private function ensure_settings_option(): void {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::get_default_settings(), '', false );
		}
	}

	/**
	 * Get normalized updater settings.
	 *
	 * @return array{repository:string, branch:string, token:string}
	 */
	private function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, self::get_default_settings() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'repository' => isset( $settings['repository'] ) && is_string( $settings['repository'] )
				? $settings['repository']
				: COMMENT_MANAGEMENT_GITHUB_REPOSITORY,
			'branch'     => isset( $settings['branch'] ) && is_string( $settings['branch'] )
				? $settings['branch']
				: 'master',
			'token'      => isset( $settings['token'] ) && is_string( $settings['token'] )
				? $settings['token']
				: '',
		);
	}
}
