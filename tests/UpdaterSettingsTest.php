<?php
/**
 * Tests for updater settings.
 *
 * @package CommentManagement
 */

namespace FiLo\CommentManagement;

use PHPUnit\Framework\TestCase;

final class UpdaterSettingsTest extends TestCase {
	private const OPTION_NAME = 'comment_management_update_settings';

	private Updater $updater;

	protected function setUp(): void {
		Test_State::reset();
		Test_State::$options[ self::OPTION_NAME ] = array(
			'repository' => 'fifoqueue/comment-management',
			'branch'     => 'master',
			'token'      => 'github_pat_existing',
		);
		$this->updater = new Updater();
	}

	public function test_accepts_valid_repository_branch_and_token(): void {
		$result = $this->updater->sanitize_settings(
			array(
				'repository' => 'example/private-plugin',
				'branch'     => 'release/1.x',
				'token'      => 'github_pat_replacement',
			)
		);

		self::assertSame( 'example/private-plugin', $result['repository'] );
		self::assertSame( 'release/1.x', $result['branch'] );
		self::assertSame( 'github_pat_replacement', $result['token'] );
		self::assertSame( array(), Test_State::$settings_errors );
	}

	public function test_blank_token_keeps_existing_secret(): void {
		$result = $this->updater->sanitize_settings(
			array(
				'repository' => 'fifoqueue/comment-management',
				'branch'     => 'master',
				'token'      => '',
			)
		);

		self::assertSame( 'github_pat_existing', $result['token'] );
	}

	public function test_clear_token_removes_existing_secret(): void {
		$result = $this->updater->sanitize_settings(
			array(
				'repository' => 'fifoqueue/comment-management',
				'branch'     => 'master',
				'clear_token' => '1',
			)
		);

		self::assertSame( '', $result['token'] );
	}

	public function test_invalid_repository_and_branch_keep_current_values(): void {
		$result = $this->updater->sanitize_settings(
			array(
				'repository' => 'https://github.com/example/repository',
				'branch'     => '../invalid',
			)
		);

		self::assertSame( 'fifoqueue/comment-management', $result['repository'] );
		self::assertSame( 'master', $result['branch'] );
		self::assertCount( 2, Test_State::$settings_errors );
	}

	public function test_invalid_token_keeps_existing_secret(): void {
		$result = $this->updater->sanitize_settings(
			array(
				'repository' => 'fifoqueue/comment-management',
				'branch'     => 'master',
				'token'      => "invalid token",
			)
		);

		self::assertSame( 'github_pat_existing', $result['token'] );
		self::assertSame( 'invalid_token', Test_State::$settings_errors[0]['code'] );
	}
}
