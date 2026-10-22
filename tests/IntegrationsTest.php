<?php
/**
 * Unit tests for the WordPress-independent parts of the Discord and Facebook integrations.
 *
 * @package AR_Net_Logger
 */

use PHPUnit\Framework\TestCase;

/**
 * Discord helpers and the Facebook post template.
 */
class IntegrationsTest extends TestCase {

	public function test_valid_webhooks() {
		$this->assertTrue( ARNL_Discord::valid_webhook( 'https://discord.com/api/webhooks/123456789012345678/AbC-dEf_123' ) );
		$this->assertTrue( ARNL_Discord::valid_webhook( 'https://discordapp.com/api/webhooks/1/x' ) );
		$this->assertTrue( ARNL_Discord::valid_webhook( 'https://ptb.discord.com/api/v10/webhooks/1/x' ) );
	}

	public function test_invalid_webhooks() {
		$this->assertFalse( ARNL_Discord::valid_webhook( 'http://discord.com/api/webhooks/1/x' ) );
		$this->assertFalse( ARNL_Discord::valid_webhook( 'https://discord.com.evil.example/api/webhooks/1/x' ) );
		$this->assertFalse( ARNL_Discord::valid_webhook( 'https://example.com/api/webhooks/1/x' ) );
		$this->assertFalse( ARNL_Discord::valid_webhook( 'https://discord.com/api/webhooks/1/x?wait=true' ) );
		$this->assertFalse( ARNL_Discord::valid_webhook( '' ) );
	}

	public function test_escape_markdown() {
		$this->assertSame( '\\*bold\\* \\_it\\_ \\`code\\` \\|\\|spoiler\\|\\|', ARNL_Discord::escape( '*bold* _it_ `code` ||spoiler||' ) );
		$this->assertSame( 'Newington, CT', ARNL_Discord::escape( 'Newington, CT' ) );
	}

	public function test_color_and_truncate() {
		$this->assertSame( 0xd63638, ARNL_Discord::color( '#d63638' ) );
		$this->assertSame( 0x2271b1, ARNL_Discord::color( 'nope' ) );
		$this->assertSame( 'abcd…', ARNL_Discord::truncate( 'abcdefgh', 5 ) );
		$this->assertSame( 'abc', ARNL_Discord::truncate( 'abc', 5 ) );
	}

	public function test_empty_embed_fields_are_dropped() {
		$this->assertSame(
			array(
				'title' => 'K7DIS',
				'color' => 1,
			),
			ARNL_Discord::clean(
				array(
					'title'       => 'K7DIS',
					'description' => '',
					'color'       => 1,
					'footer'      => array( 'text' => '' ),
				)
			)
		);
	}

	public function test_template_fills_placeholders_and_drops_empty_lines() {
		$text = ARNL_Facebook::render_template(
			"📻 {net}\n🗓 {date}, {time}\n🎙 Net Control: {ncs}\n{topic}\nCheck in: {url}\nPlain line",
			array(
				'net'   => 'Club Net',
				'date'  => 'Tuesday, October 28, 2026',
				'time'  => '7:00 pm',
				'ncs'   => '',
				'topic' => '',
				'url'   => 'https://example.org/net/club/?p=1',
			)
		);
		$this->assertSame( "📻 Club Net\n🗓 Tuesday, October 28, 2026, 7:00 pm\nCheck in: https://example.org/net/club/?p=1\nPlain line", $text );
	}

	public function test_template_keeps_unknown_placeholders() {
		$this->assertSame( 'Hello {nobody} Net', ARNL_Facebook::render_template( 'Hello {nobody} {net}', array( 'net' => 'Net' ) ) );
	}
}
