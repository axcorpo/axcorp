<?php

namespace tests\unit;

use PHPUnit\Framework\TestCase;

/**
 * Configuration-level guarantees that have no natural home in a behavioural test.
 *
 * Each of these was found switched off during the assessment, and each is a single line
 * away from being switched off again by accident. The config files are read as text
 * rather than required: they pull in params-local.php, which is generated per
 * environment and is not in the repository, so requiring them would fatal. Asserting
 * against a booted Yii::$app would be worse — the test harness builds its own component
 * set, so it would only confirm what the harness configured.
 */
class SecurityConfigTest extends TestCase
{
	/**
	 * @param string $relative
	 * @return string
	 */
	private function source($relative)
	{
		// tests/unit -> tests -> master -> repository root
		$path = dirname(__DIR__, 3) . '/' . $relative;
		$this->assertFileExists($path);

		return file_get_contents($path);
	}

	/**
	 * @return array
	 */
	public function webAppConfigProvider()
	{
		return [
			'frontend' => ['master/frontend/config/main.php'],
			'backend' => ['master/backend/config/main.php'],
		];
	}

	/**
	 * @dataProvider webAppConfigProvider
	 * @param string $path
	 */
	public function testCsrfValidationIsEnabled($path)
	{
		$source = $this->source($path);

		$this->assertStringContainsString(
			"'enableCsrfValidation' => true",
			$source,
			"{$path} must validate CSRF tokens; the machine-to-machine endpoints opt out individually"
		);
		$this->assertStringNotContainsString(
			"'enableCsrfValidation' => false",
			$source,
			"{$path} still turns CSRF validation off somewhere"
		);
	}

	/**
	 * @dataProvider webAppConfigProvider
	 * @param string $path
	 */
	public function testTheBrowserCookiesAreHttpOnlyAndSameSite($path)
	{
		$source = $this->source($path);

		// One for the session, one for the identity cookie, one for the CSRF cookie.
		$this->assertSame(
			3,
			substr_count($source, "'httpOnly' => true"),
			"{$path} must mark all three browser cookies httpOnly"
		);
		$this->assertSame(
			3,
			substr_count($source, "'sameSite' => 'Lax'"),
			"{$path} must set sameSite on all three browser cookies"
		);
		$this->assertSame(
			3,
			substr_count($source, "'secure' => YII_ENV_PROD"),
			"{$path} must mark all three browser cookies secure wherever TLS is in front"
		);
	}

	/**
	 * @dataProvider webAppConfigProvider
	 * @param string $path
	 */
	public function testTheCookiesAreNotHardWiredSecure($path)
	{
		// Dev runs on plain HTTP; a flat `'secure' => true` would have the browser drop
		// the session cookie there and log everyone out on every request.
		$this->assertStringNotContainsString("'secure' => true", $this->source($path));
	}

	public function testTheSecurityHeadersAreSet()
	{
		$htaccess = $this->source('master/.htaccess');

		foreach ([
			'Strict-Transport-Security',
			'X-Frame-Options',
			'X-Content-Type-Options',
			'Referrer-Policy',
			'frame-ancestors',
		] as $header) {
			$this->assertStringContainsString($header, $htaccess, "{$header} must be sent");
		}
	}

	/**
	 * The frontend and the admin share BOTAISESSID at path /, so whichever answers last
	 * writes the cookie. Split attributes would make the flags depend on the route.
	 */
	public function testBothAppsNameTheSameSessionCookie()
	{
		$this->assertStringContainsString(
			"'name' => 'BOTAISESSID'",
			$this->source('master/frontend/config/main.php')
		);
		$this->assertStringContainsString(
			"'name' => 'BOTAISESSID'",
			$this->source('master/backend/config/main.php'),
			'the cookie attribute assertions above only mean anything while both apps share it'
		);
	}

	public function testTheUploadsDirectoryRefusesToExecuteAnything()
	{
		$htaccess = $this->source('master/uploads/.htaccess');

		$this->assertStringContainsString('php_flag engine off', $htaccess);
		$this->assertStringContainsString('SetHandler none', $htaccess);
		$this->assertMatchesRegularExpression(
			'/RemoveHandler[^\n]*\.php\b/',
			$htaccess,
			'the PHP handlers must be unbound in the uploads tree'
		);
	}
}
