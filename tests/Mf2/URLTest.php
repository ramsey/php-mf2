<?php

/**
 * Tests of the URL resolver within mf2\Parser
 */

namespace Mf2\Parser\Test;

use Mf2;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class URLTest extends TestCase {
	protected function set_up() {
		date_default_timezone_set('Europe/London');
	}

	public function testRemoveLeadingDotSlash() {
		$input = '../one/two';
		mf2\removeLeadingDotSlash($input);
		$this->assertEquals('one/two', $input);

		$input = './one/two';
		mf2\removeLeadingDotSlash($input);
		$this->assertEquals('one/two', $input);
	}

	public function testRemoveLeadingSlashDot() {
		$input = '/./one/two';
		mf2\removeLeadingSlashDot($input);
		$this->assertEquals('/one/two', $input);

		$input = '/.';
		mf2\removeLeadingSlashDot($input);
		$this->assertEquals('/', $input);

		$input = '/./../';
		mf2\removeLeadingSlashDot($input);
		$this->assertEquals('/../', $input);

		$input = '/./../../g';
		mf2\removeLeadingSlashDot($input);
		$this->assertEquals('/../../g', $input);
	}

	public function testRemoveOneDirLevel() {
		$input = '/../../g';
		$output = '/a/b/c';
		mf2\removeOneDirLevel($input, $output);
		$this->assertEquals('/../g', $input);
		$this->assertEquals('/a/b', $output);

		$input = '/..';
		$output = '/a/b/c';
		mf2\removeOneDirLevel($input, $output);
		$this->assertEquals('/', $input);
		$this->assertEquals('/a/b', $output);
	}

	public function testRemoveLoneDotDot() {
		$input = '.';
		mf2\removeLoneDotDot($input);
		$this->assertEquals('', $input);

		$input = '..';
		mf2\removeLoneDotDot($input);
		$this->assertEquals('', $input);
	}

	public function testMoveOneSegmentFromInput() {
		$input = '/a/b/c/./../../g';
		$output = '';
		mf2\moveOneSegmentFromInput($input, $output);
		$this->assertEquals('/b/c/./../../g', $input);
		$this->assertEquals('/a', $output);

		$input = '/b/c/./../../g';
		$output = '/a';
		mf2\moveOneSegmentFromInput($input, $output);
		$this->assertEquals('/c/./../../g', $input);
		$this->assertEquals('/a/b', $output);

		$input = '/c/./../../g';
		$output = '/a/b';
		mf2\moveOneSegmentFromInput($input, $output);
		$this->assertEquals('/./../../g', $input);
		$this->assertEquals('/a/b/c', $output);

		$input = '/g';
		$output = '/a';
		mf2\moveOneSegmentFromInput($input, $output);
		$this->assertEquals('', $input);
		$this->assertEquals('/a/g', $output);
	}

	/**
	 * @dataProvider removeDotSegmentsData
	 */
	public function testRemoveDotSegments($assert, $path, $expected) {
		$actual = mf2\removeDotSegments($path);
		$this->assertEquals($expected, $actual, $assert);
	}

	public function removeDotSegmentsData() {
		return [
			['Should remove .. and .',
				'/a/b/c/./../../g', '/a/g'],
			['Should remove ../..',
				'/a/b/c/d/../../../g', '/a/g'],
			['Should not add leading slash',
				'a/b/c', 'a/b/c'],

		];
	}

	public function testNoPathOnBase() {
		$actual = mf2\resolveUrl('https://example.com', '');
		$this->assertEquals('https://example.com/', $actual);

		$actual = mf2\resolveUrl('https://example.com', '#');
		$this->assertEquals('https://example.com/#', $actual);

		$actual = mf2\resolveUrl('https://example.com', '#thing');
		$this->assertEquals('https://example.com/#thing', $actual);
	}

	public function testMisc() {
		$expected = 'http://a/b/c/g';
		$actual = mf2\resolveUrl('http://a/b/c/d;p?q', './g');
		$this->assertEquals($expected, $actual);

		$expected = 'http://a/b/c/g/';
		$actual = mf2\resolveUrl('http://a/b/c/d;p?q', './g/');
		$this->assertEquals($expected, $actual);

		$expected = 'http://a/b/';
		$actual = mf2\resolveUrl('http://a/b/c/d;p?q', '..');
		$this->assertEquals($expected, $actual);
	}

	/** as per https://github.com/indieweb/php-mf2/issues/35 */
	public function testResolvesProtocolRelativeUrlsCorrectly() {
		$expected = 'https://cdn.example.com/thing/asset.css';
		$actual = Mf2\resolveUrl('https://example.com', '//cdn.example.com/thing/asset.css');
		$this->assertEquals($expected, $actual);

		$expected = 'https://cdn.example.com/thing/asset.css';
		$actual = Mf2\resolveUrl('https://example.com', '//cdn.example.com/thing/asset.css');
		$this->assertEquals($expected, $actual);
	}

	/**
	 * @dataProvider dataProvider
	 */
  public function testReturnsUrlIfAbsolute($assert, $base, $url, $expected) {
    $actual = mf2\resolveUrl($base, $url);

    $this->assertEquals($expected, $actual, $assert);
  }

	public function dataProvider() {
		// seriously, please update to PHP 5.4 so I can use nice array syntax ;)
		// fail message, base, url, expected
		$cases = [
			['Should return absolute URL unchanged',
				'https://example.com', 'https://example.com', 'https://example.com'],

			['Should return root given blank path',
				'https://example.com', '', 'https://example.com/'],

			['Should return input unchanged given full URL and blank path',
				'https://example.com/something', '', 'https://example.com/something'],

			['Should handle blank base URL',
				'', 'https://example.com', 'https://example.com'],

			['Should resolve fragment ID',
				'https://example.com', '#thing', 'https://example.com/#thing'],

			['Should resolve blank fragment ID',
				'https://example.com', '#', 'https://example.com/#'],

			['Should resolve same level URL',
				'https://example.com', 'thing', 'https://example.com/thing'],

			['Should resolve directory level URL',
				'https://example.com', './thing', 'https://example.com/thing'],

			['Should resolve parent level URL at root level',
				'https://example.com', '../thing', 'https://example.com/thing'],

			['Should resolve nested URL',
				'https://example.com/something', 'another', 'https://example.com/another'],

			['Should ignore query strings in base url',
				'https://example.com/index.php?url=https://example.org', '/thing', 'https://example.com/thing'],

			['Should resolve query strings',
				'https://example.com/thing', '?stuff=yes', 'https://example.com/thing?stuff=yes'],

			['Should resolve dir level query strings',
				'https://example.com', './?thing=yes', 'https://example.com/?thing=yes'],

			['Should resolve up one level from root domain',
				'https://example.com', 'path/to/the/../file', 'https://example.com/path/to/file'],

			['Should resolve up one level from base with path',
				'https://example.com/path/the', 'to/the/../file', 'https://example.com/path/to/file'],

			// Tests from webignition library

			['relative add host from base',
				'http://www.example.com', 'server.php', 'http://www.example.com/server.php'],

			['relative add scheme host pass from base',
				'http://:pass@www.example.com', 'server.php', 'http://:pass@www.example.com/server.php'],

			['relative add scheme host user pass from base',
				'http://user:pass@www.example.com', 'server.php', 'http://user:pass@www.example.com/server.php'],

			['relative base has file path',
				'https://example.com/index.html', 'example.html', 'https://example.com/example.html'],

			['input has absolute path',
				'http://www.example.com/pathOne/pathTwo/pathThree', '/server.php?param1=value1', 'http://www.example.com/server.php?param1=value1'],

			['test absolute url with path',
				'http://www.example.com/', 'http://www.example.com/pathOne', 'http://www.example.com/pathOne'],

			['testRelativePathIsTransformedIntoCorrectAbsoluteUrl',
				'http://www.example.com/pathOne/pathTwo/pathThree', 'server.php?param1=value1', 'http://www.example.com/pathOne/pathTwo/server.php?param1=value1'],

			['testAbsolutePathHasDotDotDirecoryAndSourceHasFileName',
				'http://www.example.com/pathOne/index.php', '../jquery.js', 'http://www.example.com/jquery.js'],

			['testAbsolutePathHasDotDotDirecoryAndSourceHasDirectoryWithTrailingSlash',
				'http://www.example.com/pathOne/', '../jquery.js', 'http://www.example.com/jquery.js'],

			['testAbsolutePathHasDotDotDirecoryAndSourceHasDirectoryWithoutTrailingSlash',
				'http://www.example.com/pathOne', '../jquery.js', 'http://www.example.com/jquery.js'],

			['testAbsolutePathHasDotDirecoryAndSourceHasFilename',
				'http://www.example.com/pathOne/index.php', './jquery.js', 'http://www.example.com/pathOne/jquery.js'],

			['testAbsolutePathHasDotDirecoryAndSourceHasDirectoryWithTrailingSlash',
				'http://www.example.com/pathOne/', './jquery.js', 'http://www.example.com/pathOne/jquery.js'],

			['testAbsolutePathHasDotDirecoryAndSourceHasDirectoryWithoutTrailingSlash',
				'http://www.example.com/pathOne', './jquery.js', 'http://www.example.com/jquery.js'],

			['testAbsolutePathIncludesPortNumber',
				'https://example.com:8080/index.html', '/photo.jpg', 'https://example.com:8080/photo.jpg']

		];

		// PHP 5.4 and before returns a different result, but either are acceptable
		if(PHP_MAJOR_VERSION <= 5 && PHP_MINOR_VERSION <= 4) {
			$cases[] = ['relative add scheme host user from base',
				'http://user:@www.example.com', 'server.php', 'http://user@www.example.com/server.php'];
		} else {
			$cases[] = ['relative add scheme host user from base',
				'http://user:@www.example.com', 'server.php', 'http://user:@www.example.com/server.php'];
		}

		// Test cases from RFC
		// http://tools.ietf.org/html/rfc3986#section-5.4

		$rfcTests = [
			["g:h", "g:h"],
			["g", "http://a/b/c/g"],
			["./g", "http://a/b/c/g"],
			["g/", "http://a/b/c/g/"],
			["/g", "http://a/g"],
			["//g", "http://g"],
			["?y", "http://a/b/c/d;p?y"],
			["g?y", "http://a/b/c/g?y"],
			["#s", "http://a/b/c/d;p?q#s"],
			["g#s", "http://a/b/c/g#s"],
			["g?y#s", "http://a/b/c/g?y#s"],
			[";x", "http://a/b/c/;x"],
			["g;x", "http://a/b/c/g;x"],
			["g;x?y#s", "http://a/b/c/g;x?y#s"],
			["", "http://a/b/c/d;p?q"],
			[".", "http://a/b/c/"],
			["./", "http://a/b/c/"],
			["..", "http://a/b/"],
			["../", "http://a/b/"],
			["../g", "http://a/b/g"],
			["../..", "http://a/"],
			["../../", "http://a/"],
			["../../g", "http://a/g"]
		];

		foreach($rfcTests as $i=>$test) {
			$cases[] = [
				'test rfc ' . $i, 'http://a/b/c/d;p?q', $test[0], $test[1]
			];
		}

		return $cases;
	}
}
