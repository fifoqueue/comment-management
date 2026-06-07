<?php
/**
 * PHPUnit bootstrap and minimal WordPress test doubles.
 *
 * @package CommentManagement
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'COMMENT_MANAGEMENT_GITHUB_REPOSITORY', 'fifoqueue/comment-management' );

	class WP_Error {
		public function __construct(
			private readonly string $code = '',
			private readonly string $message = ''
		) {
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}

	class WP_Comment {
		public int $comment_ID;
		public string $comment_content;

		public function __construct( int $comment_id, string $comment_content = 'Original' ) {
			$this->comment_ID      = $comment_id;
			$this->comment_content = $comment_content;
		}
	}
}

namespace FiLo\CommentManagement {
	final class Test_State {
		public static ?\WP_Comment $comment = null;
		public static bool $can_edit = true;
		public static bool $can_moderate = true;
		public static mixed $mutation_result = true;
		public static ?array $updated_comment = null;
		public static array $options = array();
		public static array $settings_errors = array();

		public static function reset(): void {
			self::$comment         = new \WP_Comment( 42 );
			self::$can_edit        = true;
			self::$can_moderate    = true;
			self::$mutation_result = true;
			self::$updated_comment = null;
			self::$options         = array();
			self::$settings_errors = array();
		}
	}

	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}

	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) ?? '' );
	}

	function get_comment( ?int $comment_id = null ): ?\WP_Comment {
		if ( null !== $comment_id && Test_State::$comment?->comment_ID !== $comment_id ) {
			return null;
		}

		return Test_State::$comment;
	}

	function current_user_can( string $capability, int ...$object_ids ): bool {
		unset( $object_ids );

		return 'moderate_comments' === $capability
			? Test_State::$can_moderate
			: Test_State::$can_edit;
	}

	function wp_trash_comment( \WP_Comment $comment ): mixed {
		unset( $comment );
		return Test_State::$mutation_result;
	}

	function wp_spam_comment( \WP_Comment $comment ): mixed {
		unset( $comment );
		return Test_State::$mutation_result;
	}

	function wp_set_comment_status( \WP_Comment $comment, string $status, bool $wp_error ): mixed {
		unset( $comment, $status, $wp_error );
		return Test_State::$mutation_result;
	}

	function wp_delete_comment( \WP_Comment $comment, bool $force_delete ): mixed {
		unset( $comment, $force_delete );
		return Test_State::$mutation_result;
	}

	function wp_kses_post( string $content ): string {
		return strip_tags( $content, '<strong><em><a><code>' );
	}

	function wp_update_comment( array $data, bool $wp_error ): mixed {
		unset( $wp_error );
		Test_State::$updated_comment = $data;

		if ( true === Test_State::$mutation_result && Test_State::$comment instanceof \WP_Comment ) {
			Test_State::$comment->comment_content = $data['comment_content'];
			return $data['comment_ID'];
		}

		return Test_State::$mutation_result;
	}

	function is_wp_error( mixed $value ): bool {
		return $value instanceof \WP_Error;
	}

	function wp_unslash( mixed $value ): mixed {
		return $value;
	}

	function get_option( string $option, mixed $default = false ): mixed {
		return Test_State::$options[ $option ] ?? $default;
	}

	function add_option(
		string $option,
		mixed $value,
		string $deprecated = '',
		bool $autoload = true
	): bool {
		unset( $deprecated, $autoload );

		if ( array_key_exists( $option, Test_State::$options ) ) {
			return false;
		}

		Test_State::$options[ $option ] = $value;
		return true;
	}

	function add_settings_error(
		string $setting,
		string $code,
		string $message,
		string $type = 'error'
	): void {
		Test_State::$settings_errors[] = compact( 'setting', 'code', 'message', 'type' );
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/class-comment-action-service.php';
	require_once dirname( __DIR__ ) . '/includes/class-updater.php';
}
