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
		public string $comment_approved;

		public function __construct(
			int $comment_id,
			string $comment_content = 'Original',
			string $comment_approved = '1'
		) {
			$this->comment_ID      = $comment_id;
			$this->comment_content = $comment_content;
			$this->comment_approved = $comment_approved;
		}
	}

	class WP_User {
		public function __construct( public string $display_name ) {
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
		public static array $comment_meta = array();
		public static array $transients = array();
		public static int $current_user_id = 7;

		public static function reset(): void {
			self::$comment         = new \WP_Comment( 42 );
			self::$can_edit        = true;
			self::$can_moderate    = true;
			self::$mutation_result = true;
			self::$updated_comment = null;
			self::$options         = array();
			self::$settings_errors = array();
			self::$comment_meta    = array();
			self::$transients      = array();
			self::$current_user_id = 7;
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
		$comment->comment_approved = 'trash';
		return Test_State::$mutation_result;
	}

	function wp_spam_comment( \WP_Comment $comment ): mixed {
		$comment->comment_approved = 'spam';
		return Test_State::$mutation_result;
	}

	function wp_set_comment_status( \WP_Comment $comment, string $status, bool $wp_error ): mixed {
		unset( $wp_error );
		$comment->comment_approved = $status;
		return Test_State::$mutation_result;
	}

	function wp_untrash_comment( \WP_Comment $comment ): mixed {
		$comment->comment_approved = '1';
		return Test_State::$mutation_result;
	}

	function wp_unspam_comment( \WP_Comment $comment ): mixed {
		$comment->comment_approved = '1';
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

	function wp_generate_uuid4(): string {
		static $counter = 0;
		++$counter;
		return sprintf( '00000000-0000-4000-8000-%012d', $counter );
	}

	function wp_generate_password(
		int $length = 12,
		bool $special_chars = true,
		bool $extra_special_chars = false
	): string {
		unset( $special_chars, $extra_special_chars );
		return str_repeat( 'a', $length - 1 ) . 'b';
	}

	function wp_json_encode( mixed $value ): string|false {
		return json_encode( $value );
	}

	function wp_salt( string $scheme = 'auth' ): string {
		return 'test-' . $scheme . '-salt';
	}

	function get_current_user_id(): int {
		return Test_State::$current_user_id;
	}

	function get_comment_meta( int $comment_id, string $key, bool $single = false ): mixed {
		unset( $single );
		return Test_State::$comment_meta[ $comment_id ][ $key ] ?? '';
	}

	function update_comment_meta( int $comment_id, string $key, mixed $value ): bool {
		Test_State::$comment_meta[ $comment_id ][ $key ] = $value;
		return true;
	}

	function delete_comment_meta( int $comment_id, string $key ): bool {
		unset( Test_State::$comment_meta[ $comment_id ][ $key ] );
		return true;
	}

	function set_transient( string $key, mixed $value, int $expiration ): bool {
		unset( $expiration );
		Test_State::$transients[ $key ] = $value;
		return true;
	}

	function get_transient( string $key ): mixed {
		return Test_State::$transients[ $key ] ?? false;
	}

	function delete_transient( string $key ): bool {
		unset( Test_State::$transients[ $key ] );
		return true;
	}

	function get_userdata( int $user_id ): \WP_User|false {
		return $user_id > 0 ? new \WP_User( 'Administrator' ) : false;
	}

	function wp_date( string $format, int $timestamp ): string {
		return gmdate( '' !== $format ? $format : 'Y-m-d H:i', $timestamp );
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
