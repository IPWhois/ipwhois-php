<?php

declare(strict_types=1);

namespace Ipwhois\Tests;

use Ipwhois\IPWhois;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering URL construction and input validation.
 *
 * No real HTTP request is sent — buildUrl() is exercised through reflection
 * so the suite can be run anywhere without an API key or network access.
 */
final class IPWhoisTest extends TestCase
{
    private function buildUrl(IPWhois $ipwhois, string $path, array $options = []): string
    {
        $ref    = new \ReflectionClass($ipwhois);
        $method = $ref->getMethod('buildUrl');
        $method->setAccessible(true);

        return $method->invoke($ipwhois, $path, $options);
    }

    public function testFreeEndpointHasNoApiKey(): void
    {
        $ipwhois = new IPWhois();
        $url    = $this->buildUrl($ipwhois, '/8.8.8.8');

        self::assertSame('https://ipwho.is/8.8.8.8', $url);
    }

    public function testPaidEndpointAppendsApiKey(): void
    {
        $ipwhois = new IPWhois('TESTKEY');
        $url    = $this->buildUrl($ipwhois, '/8.8.8.8');

        self::assertStringStartsWith('https://ipwhois.pro/8.8.8.8?', $url);
        self::assertStringContainsString('key=TESTKEY', $url);
    }

    public function testHttpsIsAlwaysUsed(): void
    {
        self::assertStringStartsWith('https://', $this->buildUrl(new IPWhois(), '/'));
        self::assertStringStartsWith('https://', $this->buildUrl(new IPWhois('K'), '/'));
    }

    public function testSslCanBeDisabled(): void
    {
        $free = new IPWhois(null, ['ssl' => false]);
        $paid = new IPWhois('K',  ['ssl' => false]);

        self::assertStringStartsWith('http://ipwho.is',  $this->buildUrl($free, '/'));
        self::assertStringStartsWith('http://ipwhois.pro', $this->buildUrl($paid, '/'));
    }

    public function testSslDefaultsToTrueWhenNotPassed(): void
    {
        // Sanity check: omitting the option keeps HTTPS on.
        self::assertStringStartsWith('https://', $this->buildUrl(new IPWhois('K', []), '/'));
    }

    public function testFieldsAreJoinedWithCommas(): void
    {
        $ipwhois = new IPWhois('K');
        $url    = $this->buildUrl($ipwhois, '/8.8.8.8', [
            'fields' => ['country', 'city', 'flag.emoji'],
        ]);

        // http_build_query encodes commas as %2C — both forms are valid HTTP.
        self::assertStringContainsString('fields=country%2Ccity%2Cflag.emoji', $url);
    }

    public function testSecurityAndRateAreFlagsNotValues(): void
    {
        $ipwhois = new IPWhois('K');
        $url    = $this->buildUrl($ipwhois, '/', [
            'security' => true,
            'rate'     => true,
        ]);

        self::assertStringContainsString('security=1', $url);
        self::assertStringContainsString('rate=1', $url);
    }

    public function testSecurityFalseIsOmitted(): void
    {
        $ipwhois = new IPWhois('K');
        $url    = $this->buildUrl($ipwhois, '/', ['security' => false]);

        self::assertStringNotContainsString('security=', $url);
    }

    public function testPerCallOptionsOverrideDefaults(): void
    {
        $ipwhois = new IPWhois('K', ['lang' => 'ru']);
        $url    = $this->buildUrl($ipwhois, '/', ['lang' => 'en']);

        self::assertStringContainsString('lang=en', $url);
        self::assertStringNotContainsString('lang=ru', $url);
    }

    public function testInvalidLanguageReturnsErrorArray(): void
    {
        $result = (new IPWhois())->lookup('8.8.8.8', ['lang' => 'klingon']);

        self::assertFalse($result['success']);
        self::assertSame('invalid_argument', $result['error_type'] ?? null);
        self::assertStringContainsString('klingon', $result['message'] ?? '');
    }

    public function testInvalidOutputReturnsErrorArray(): void
    {
        $result = (new IPWhois())->lookup('8.8.8.8', ['output' => 'yaml']);

        self::assertFalse($result['success']);
        self::assertSame('invalid_argument', $result['error_type'] ?? null);
        self::assertStringContainsString('yaml', $result['message'] ?? '');
    }

    public function testBulkLookupRefusesEmptyList(): void
    {
        $result = (new IPWhois('K'))->bulkLookup([]);

        self::assertFalse($result['success']);
        self::assertSame('invalid_argument', $result['error_type'] ?? null);
    }

    public function testBulkLookupRefusesMoreThanLimit(): void
    {
        $tooMany = array_fill(0, IPWhois::BULK_LIMIT + 1, '8.8.8.8');
        $result  = (new IPWhois('K'))->bulkLookup($tooMany);

        self::assertFalse($result['success']);
        self::assertSame('invalid_argument', $result['error_type'] ?? null);
    }

    public function testBulkUrlIsCommaSeparated(): void
    {
        $ipwhois = new IPWhois('K');
        $url    = $this->buildUrl($ipwhois, '/bulk/' . implode(',', ['8.8.8.8', '1.1.1.1']));

        self::assertStringContainsString('/bulk/8.8.8.8,1.1.1.1', $url);
    }

    public function testFluentSettersReturnSelf(): void
    {
        $ipwhois = new IPWhois();

        self::assertSame($ipwhois, $ipwhois->setLanguage('en'));
        self::assertSame($ipwhois, $ipwhois->setFields(['country']));
        self::assertSame($ipwhois, $ipwhois->setSecurity(true));
        self::assertSame($ipwhois, $ipwhois->setRate(false));
        self::assertSame($ipwhois, $ipwhois->setTimeout(5));
        self::assertSame($ipwhois, $ipwhois->setConnectTimeout(2));
        self::assertSame($ipwhois, $ipwhois->setUserAgent('test/1.0'));
    }

    public function testSetLanguageAffectsSubsequentRequests(): void
    {
        $ipwhois = (new IPWhois('K'))->setLanguage('de');
        $url    = $this->buildUrl($ipwhois, '/');

        self::assertStringContainsString('lang=de', $url);
    }
}
