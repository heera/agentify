<?php
/**
 * Schema (JSON-LD) privacy. A published-but-password-protected post (the HTML
 * site shows only a password form for it) must never have its gated body
 * surfaced as structured data — most damagingly the FAQPage node, which lifts
 * the Q&A straight out of post_content. Likewise non-published statuses get no
 * per-post node. The site-level WebSite/entity nodes still stand.
 *
 * Regression test for the whole-plugin audit: the password guard that
 * Markdown::post() has was missing from Schema::output() / faq_node().
 *
 * @package Agentimus\Tests
 */

namespace Agentimus\Tests;

use Agentimus\Schema;
use Agentimus\Settings;
use PHPUnit\Framework\TestCase;

final class SchemaPrivacyTest extends TestCase {

	/** Two disclosure blocks → a valid FAQPage; the answers are the "secret". */
	const FAQ_HTML = '<details><summary>What is the secret?</summary><p>The secret answer is 42.</p></details>'
		. '<details><summary>Is it hidden?</summary><p>Yes, members only content.</p></details>';

	protected function setUp(): void {
		_af_reset_options();
	}

	protected function tearDown(): void {
		_af_reset_options();
	}

	/** Render Schema::output() for a single post built from the given overrides. */
	private function render( array $overrides = array() ): string {
		$post = (object) array_merge(
			array(
				'ID'            => 5,
				'post_status'   => 'publish',
				'post_password' => '',
				'post_type'     => 'post',
				'post_title'    => 'The Protected Post Title',
				'post_content'  => self::FAQ_HTML,
			),
			$overrides
		);
		$GLOBALS['_af_posts'][5]        = $post;
		$GLOBALS['_af_current_post_id'] = 5;
		$GLOBALS['_af_is_singular']     = true;

		$schema = new Schema( new Settings() );
		ob_start();
		$schema->output();
		return (string) ob_get_clean();
	}

	public function test_password_protected_post_faq_is_not_emitted() {
		$out = $this->render( array( 'post_password' => 'hunter2' ) );

		$this->assertStringNotContainsString( 'The secret answer is 42', $out );
		$this->assertStringNotContainsString( 'members only content', $out );
		$this->assertStringNotContainsString( 'FAQPage', $out );
		// The per-post Article node (title/dates) is gated too.
		$this->assertStringNotContainsString( 'The Protected Post Title', $out );
		// …but the site-level identity is still published.
		$this->assertStringContainsString( 'WebSite', $out );
	}

	public function test_non_published_post_gets_no_per_post_node() {
		$out = $this->render( array( 'post_status' => 'draft' ) );

		$this->assertStringNotContainsString( 'The secret answer is 42', $out );
		$this->assertStringNotContainsString( 'FAQPage', $out );
		$this->assertStringNotContainsString( 'The Protected Post Title', $out );
		$this->assertStringContainsString( 'WebSite', $out );
	}

	public function test_public_post_faq_is_emitted() {
		// Proves the guard didn't break normal behaviour: a public post with the
		// same content DOES publish the FAQPage + Article nodes.
		$out = $this->render();

		$this->assertStringContainsString( 'FAQPage', $out );
		$this->assertStringContainsString( 'The secret answer is 42', $out );
		$this->assertStringContainsString( 'The Protected Post Title', $out );
	}
}
