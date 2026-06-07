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
	}

	public function test_permanent_delete_can_run_in_cli_context(): void {
		Test_State::$can_edit = false;

		$result = $this->service->execute( 42, 'delete', null, false );

		self::assertIsArray( $result );
		self::assertTrue( $result['removed'] );
	}

	public function test_returns_core_mutation_errors(): void {
		Test_State::$mutation_result = new \WP_Error( 'database_error', 'Database error.' );

		$result = $this->service->execute( 42, 'unapprove' );

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'database_error', $result->get_error_code() );
	}
}
