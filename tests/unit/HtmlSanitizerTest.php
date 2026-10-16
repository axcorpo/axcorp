<?php

namespace tests\unit;

use common\helpers\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * The allow-list sanitiser behind the helpdesk and the admin content previews.
 *
 * A support ticket is written by any registered customer and printed as HTML in the
 * screens an administrator reads, which made it the shortest path from an ordinary
 * account into an administrator's session. Same shape as the workspace app's
 * Announcement::sanitizeContentHtml(): keep what the editor offers, drop the rest.
 */
class HtmlSanitizerTest extends TestCase
{
	public function testTheEditorsFormattingSurvives()
	{
		$html = '<p>A <strong>ticket</strong> with <em>detail</em></p><ul><li>one</li><li>two</li></ul>';

		$this->assertSame($html, HtmlSanitizer::richText($html));
	}

	/**
	 * @dataProvider payloadProvider
	 * @param string $html
	 * @param string $mustNotSurvive
	 */
	public function testExecutableContentIsStripped($html, $mustNotSurvive)
	{
		$out = HtmlSanitizer::richText($html);

		$this->assertStringNotContainsString($mustNotSurvive, strtolower($out));
	}

	/**
	 * @return array
	 */
	public function payloadProvider()
	{
		return [
			'script tag' => ['<p>hi</p><script>alert(1)</script>', '<script'],
			'event handler' => ['<p onclick="alert(1)">hi</p>', 'onclick'],
			'image error handler' => ['<img src=x onerror="alert(1)">', 'onerror'],
			'iframe' => ['<iframe src="https://attacker.example"></iframe>', '<iframe'],
			'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
			'svg payload' => ['<svg onload="alert(1)"></svg>', 'onload'],
			'style block' => ['<style>body{display:none}</style>', '<style'],
			'form' => ['<form action="https://attacker.example"><input name="p"></form>', '<form'],
			'object' => ['<object data="x"></object>', '<object'],
			// The blocklist this replaces matched "<script" literally and missed the rest.
			// What matters is that no tag survives -- leftover text reading "alert(1)" is
			// just text, which is the point of an allow-list.
			'uppercase script' => ['<SCRIPT>alert(1)</SCRIPT>', '<script'],
			'broken-up tag' => ['<scr<script>ipt>alert(1)</script>', '<script'],
		];
	}

	public function testPositioningStylesAreStrippedButAlignmentSurvives()
	{
		$out = HtmlSanitizer::richText('<p style="text-align:right;position:fixed;font-size:90px;">x</p>');

		$this->assertStringContainsString('text-align', $out);
		$this->assertStringNotContainsString('position', $out, 'nothing may be overlaid on the page around it');
		$this->assertStringNotContainsString('font-size', $out);
	}

	public function testLinksSurviveAndAreMarkedNofollow()
	{
		$out = HtmlSanitizer::richText('<p><a href="https://example.test/x" target="_blank">x</a></p>');

		$this->assertStringContainsString('href="https://example.test/x"', $out);
		$this->assertStringContainsString('nofollow', $out);
	}

	public function testEmptyValuesPassThrough()
	{
		$this->assertNull(HtmlSanitizer::richText(null));
		$this->assertSame('', HtmlSanitizer::richText(''));
	}

	/**
	 * @dataProvider unsafeHrefProvider
	 * @param string $url
	 */
	public function testUnsafeSchemesAreRefusedForHrefs($url)
	{
		// Encoding is no help here: `javascript:alert(1)` contains no markup and comes
		// through Html::encode() unchanged, so only the scheme check stops it.
		$this->assertNull(HtmlSanitizer::href($url));
	}

	/**
	 * @return array
	 */
	public function unsafeHrefProvider()
	{
		return [
			'javascript' => ['javascript:alert(1)'],
			'uppercase javascript' => ['JavaScript:alert(1)'],
			'data uri' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
			'vbscript' => ['vbscript:msgbox(1)'],
			'file' => ['file:///etc/passwd'],
		];
	}

	/**
	 * @dataProvider safeHrefProvider
	 * @param string $url
	 * @param string $expected
	 */
	public function testNavigableUrlsAreKept($url, $expected)
	{
		$this->assertSame($expected, HtmlSanitizer::href($url));
	}

	/**
	 * @return array
	 */
	public function safeHrefProvider()
	{
		return [
			'https' => ['https://example.test/x', 'https://example.test/x'],
			'http' => ['http://example.test/x', 'http://example.test/x'],
			'mailto' => ['mailto:a@example.test', 'mailto:a@example.test'],
			'relative' => ['/about', '/about'],
			'a bare host gets a scheme' => ['example.test/x', 'http://example.test/x'],
		];
	}

	public function testAnEmptyUrlIsNotALink()
	{
		$this->assertNull(HtmlSanitizer::href(null));
		$this->assertNull(HtmlSanitizer::href('   '));
	}

	/**
	 * The payload the assessment left in a carousel item, printed as HTML on the public
	 * homepage.
	 *
	 * @dataProvider websitePayloads
	 * @param string $payload
	 */
	public function testWebsiteContentDropsScriptableMarkup($payload)
	{
		$clean = HtmlSanitizer::websiteContent($payload);

		$this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
		$this->assertStringNotContainsStringIgnoringCase('onclick', $clean);
		$this->assertStringNotContainsStringIgnoringCase('onload', $clean);
		$this->assertStringNotContainsStringIgnoringCase('<script', $clean);
		$this->assertStringNotContainsStringIgnoringCase('<img', $clean);
		$this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
	}

	public function websitePayloads()
	{
		return [
			'img onerror' => ['<img src=x onerror="alert(\'XSS test\')" />'],
			'event handler' => ['<p onclick="alert(1)">x</p>'],
			'script' => ['<div>x</div><script>alert(1)</script>'],
			'javascript link' => ['<a class="btn1" href="javascript:alert(1)">x</a>'],
			'css url' => ['<div style="background:url(javascript:alert(1))">x</div>'],
			'svg' => ['<svg onload=alert(1)></svg>'],
		];
	}

	public function testTheWebsiteMarkupSurvives()
	{
		// The shape of the real carousel content: wow.js classes and data, button links.
		$html = '<p class="wow animated fadeInUp" style="width: 100%; text-align: center;" data-wow-duration="1s">Text</p>'
			. '<div class="buttons wow fadeInUp" data-wow-duration="2s"><a class="btn1" href="login">Sign In</a></div>';

		$clean = HtmlSanitizer::websiteContent($html);

		$this->assertStringContainsString('class="wow animated fadeInUp"', $clean);
		$this->assertStringContainsString('data-wow-duration="1s"', $clean);
		$this->assertStringContainsString('text-align:center', $clean);
		$this->assertStringContainsString('<a class="btn1" href="login">Sign In</a>', $clean);
	}

	/**
	 * What every TEXT column goes through on save (CommonActiveRecord::sanitizeStoredText()).
	 *
	 * @dataProvider executablePayloads
	 * @param string $payload
	 * @param string $kept what must survive around the removed part
	 */
	public function testStoredHtmlLosesOnlyItsExecutableParts($payload, $kept)
	{
		$clean = HtmlSanitizer::stripExecutable($payload);

		$this->assertDoesNotMatchRegularExpression(HtmlSanitizer::EXECUTABLE_PATTERN, html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $clean);
		$this->assertStringContainsString($kept, $clean);
	}

	public function executablePayloads()
	{
		return [
			'img onerror' => ['<p>Text</p><img src=x onerror="alert(\'XSS test\')" />', '<p>Text</p>'],
			'script' => ['<p>Text</p><script>alert(1)</script>', '<p>Text</p>'],
			'entity-encoded handler' => ['<p>Text</p><img src=x o&#110;error=alert(1)>', '<p>Text</p>'],
			'javascript link' => ['<a href=" java&#9;script:alert(1)">Link</a>', 'Link'],
			'iframe' => ['<p>Text</p><iframe src="//evil"></iframe>', '<p>Text</p>'],
			'svg' => ['<p>Text</p><svg onload=alert(1)></svg>', '<p>Text</p>'],
			'css expression' => ['<div style="width:expression(alert(1))">Box</div>', 'Box'],
		];
	}

	public function testATemplateWithoutExecutablePartsIsLeftByteForByte()
	{
		// Tables, inline styles, a logo and shortcodes: what receipts and emails are made of.
		$template = '<table style="width: 100%; border-collapse: collapse;" border="1"><tr>'
			. '<td style="page-break-after: always;"><img src="{{LOGO_URL}}" width="120" alt="logo" /></td>'
			. '<td><a href="{{ACCOUNT_ACTIVATION_URL}}">{{APP_NAME}}</a> &nbsp;&icirc;</td></tr></table>';

		$this->assertSame($template, HtmlSanitizer::stripExecutable($template));
	}

	public function testAnEmbeddedImageSurvivesButAnHtmlDataUrlDoesNot()
	{
		$clean = HtmlSanitizer::stripExecutable(
			'<img src="data:image/png;base64,iVBORw0KGgo=" alt="a" /><a href="data:text/html;base64,PHNjcmlwdD4=">b</a>'
		);

		$this->assertStringContainsString('src="data:image/png;base64,iVBORw0KGgo="', $clean);
		$this->assertStringNotContainsString('data:text/html', $clean);
	}

	/**
	 * What every VARCHAR/CHAR column goes through on save.
	 */
	public function testPlainTextLosesItsTagsButNotItsText()
	{
		$this->assertSame('Titlu', HtmlSanitizer::plainText('<img src=x onerror=alert(1)>Titlu'));
		$this->assertSame('Titlu', HtmlSanitizer::plainText('<script>alert(1)</script>Titlu'));
		$this->assertSame('< 5 ani & peste', HtmlSanitizer::plainText('< 5 ani & peste'));
		$this->assertSame('Popescu Ion', HtmlSanitizer::plainText('Popescu Ion'));
		$this->assertNull(HtmlSanitizer::plainText(null));
	}

	public function testTheCarouselTitleKeepsItsLineBreak()
	{
		// The seeded slide titles break the line with <br>, and the homepage prints them as HTML.
		$clean = HtmlSanitizer::websiteContent('Toate produsele <br>într-un singur loc!<img src=x onerror=alert(1)>');

		$this->assertStringContainsString('<br', $clean);
		$this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
	}
}
