<?php
/**
 * Tests for Comment_Action_Service.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

use PHPUnit\Framework\TestCase;

final class CommentActionServiceTest extends TestCase {
	private Comment_Action_Service $service;

	protected function setUp(): void {
		Test_State::reset();
		$this->service = new Comment_Action_Service();
	}

	public function test_rejects_unknown_action(): void {
		$result = $this->service->execute( 42, 'publish' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'comment_management_invalid_action', $result->get_error_code() );
	}

	public function test_rejects_missing_comment(): void {
		Test_State::$comment = null;

		$result = $this->service->execute( 42, 'trash' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'comment_management_comment_not_found', $result->get_error_code() );
	}

	public function test_checks_comment_capability(): void {
		Test_State::$can_edit = false;

		$result = $this->service->execute( 42, 'spam' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'comment_management_forbidden', $result->get_error_code() );
	}

	public function test_requires_comment_moderation_capability(): void {
		Test_State::$can_moderate = false;

		$result = $this->service->execute( 42, 'trash' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'comment_management_forbidden', $result->get_error_code() );
	}

	public function test_edits_and_sanitizes_comment_content(): void {
		$result = $this->service->execute(
			42,
			'edit',
			'<strong>Allowed</strong><script>alert(1)</script>'
		);

		self::assertIsArray( $result );
		self::assertSame(
			'<strong>Allowed</strong>alert(1)',
			Test_State::$updated_comment['comment_content']
		);
		self::assertSame( 'edit', $result['action'] );
		self::assertFalse( $result['removed'] );
		self::assertCount(
			1,
			Test_State::$comment_meta[42]['_comment_management_edit_history']
		);
	}

	public function test_returns_and_restores_edit_history(): void {
		$this->service->execute( 42, 'edit', 'Second version' );

		$history = $this->service->execute( 42, 'history' );
		self::assertSame( 'Original', $history['history'][0]['content'] );

		$restored = $this->service->execute(
			42,
			'restore_revision',
			null,
			$history['history'][0]['id']
		);

		self::assertIsArray( $restored );
		self::assertSame( 'Original', Test_State::$comment->comment_content );
	}

	public function test_edit_history_is_limited_to_twenty_revisions(): void {
		for ( $index = 1; $index <= 21; ++$index ) {
			$this->service->execute( 42, 'edit', 'Version ' . $index );
		}

		$history = $this->service->execute( 42, 'history' );

		self::assertCount( 20, $history['history'] );
		self::assertSame( 'Version 20', $history['history'][0]['content'] );
		self::assertSame( 'Version 1', $history['history'][19]['content'] );
	}

	public function test_moderation_action_can_be_undone_once(): void {
		$result = $this->service->execute( 42, 'trash' );

		self::assertSame( 'trash', Test_State::$comment->comment_approved );
		self::assertNotEmpty( $result['undo_token'] );

		$undone = $this->service->execute(
			42,
			'undo',
			null,
			$result['undo_token']
		);

		self::assertIsArray( $undone );
		self::assertSame( '1', Test_State::$comment->comment_approved );

		$reused = $this->service->execute(
			42,
			'undo',
			null,
			$result['undo_token']
		);
		self::assertInstanceOf( \WP_Error::class, $reused );
	}

	public function test_unapprove_can_be_undone_to_approved_status(): void {
		$result = $this->service->execute( 42, 'unapprove' );

		self::assertSame( 'hold', Test_State::$comment->comment_approved );

		$this->service->execute( 42, 'undo', null, $result['undo_token'] );

		self::assertSame( 'approve', Test_State::$comment->comment_approved );
	}

	public function test_undo_token_is_bound_to_user(): void {
		$result = $this->service->execute( 42, 'spam' );
		Test_State::$current_user_id = 9;

		$undone = $this->service->execute(
			42,
			'undo',
			null,
			$result['undo_token']
		);

		self::assertInstanceOf( \WP_Error::class, $undone );
		self::assertSame( 'spam', Test_State::$comment->comment_approved );
	}

	public function test_tampered_undo_token_is_rejected(): void {
		$result = $this->service->execute( 42, 'trash' );
		$token  = $result['undo_token'] . 'x';

		$undone = $this->service->execute( 42, 'undo', null, $token );

		self::assertInstanceOf( \WP_Error::class, $undone );
		self::assertSame( 'trash', Test_State::$comment->comment_approved );
	}

	public function test_returns_core_mutation_errors(): void {
		Test_State::$mutation_result = new \WP_Error( 'database_error', 'Database error.' );

		$result = $this->service->execute( 42, 'unapprove' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'database_error', $result->get_error_code() );
	}
}
