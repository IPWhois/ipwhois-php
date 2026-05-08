<?php

declare(strict_types=1);

namespace Ipwhois\Tests;

use Ipwhois\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering URL construction and input validation.
 *
 * No real HTTP request is sent — buildUrl() is exercised through reflection
 * so the suite can be run anywhere without an API key or network access.
 */
final class ClientTest extends TestCase
{
    private function buildUrl(Client $client, string $path, array $options = []): string
    {
        $ref    = new \ReflectionClass($client);
        $method = $ref->getMethod('buildUrl');
        $method->setAccessible(true);

        return $method->invoke($client, $path, $options);
    }

    public function testFreeEndpointHasNoApiKey(): void
    {
        $client = new Client();
        $url    = $this->buildUrl($client, '/8.8.8.8');

        self::assertSame('https://ipwho.is/8.8.8.8', $url);
    }

    public function testPaidEndpointAppendsApiKey(): void
    {
        $client = new Client('TESTKEY');
        $url    = $this->buildUrl($client, '/8.8.8.8');

        self::assertStringStartsWith('https://ipwhois.pro/8.8.8.8?', $url);
        self::assertStringContainsString('key=TESTKEY', $url);
    }

    public function testHttpsIsAlwaysUsed(): void
    {
        self::assertStringStartsWith('https://', $this->buildUrl(new Client(), '/'));
        self::assertStringStartsWith('https://', $this->buildUrl(new Client('K'), '/'));
    }

    public function testSslCanBeDisabled(): void
    {
        $free = new Client(null, ['ssl' => false]);
        $paid = new Client('K',  ['ssl' => false]);

        self::assertStringStartsWith('http://ipwho.is',  $this->buildUrl($free, '/'));
        self::assertStringStartsWith('http://ipwhois.pro', $this->buildUrl($paid, '/'));
    }

    public function testSslDefaultsToTrueWhenNotPassed(): void
    {
        // Sanity check: omitting the option keeps HTTPS on.
        self::assertStringStartsWith('https://', $this->buildUrl(new Client('K', []), '/'));
    }

    public function testFieldsAreJoinedWithCommas(): void
    {
        $client = new Client('K');
        $url    = $this->buildUrl($client, '/8.8.8.8', [
            'fields' => ['country', 'city', 'flag.emoji'],
        ]);

        // http_build_query encodes commas as %2C — both forms are valid HTTP.
        self::assertStringContainsString('fields=country%2Ccity%2Cflag.emoji', $url);
    }

    public function testSecurityAndRateAreFlagsNotValues(): void
    {
        $client = new Client('K');
        $url    = $this->buildUrl($client, '/', [
            'security' => true,
            'rate'     => true,
        ]);

        self::assertStringContainsString('security=1', $url);
        self::assertStringContainsString('rate=1', $url);
    }

    public function testSecurityFalseIsOmitted(): void
    {
        $client = new Client('K');
        $url    = $this->buildUrl($client, '/', ['security' => false]);

        self::assertStringNotContainsString('security=', $url);
    }

    public function testPerCallOptionsOverrideDefaults(): void
    {
        $client = new Client('K', ['lang' => 'ru']);
        $url    = $this->buildUrl($client, '/', ['lang' => 'en']);

        self::assertStringContainsString('lang=en', $url);
        self::assertStringNotContainsString('lang=ru', $url);
    }

    public function testInvalidLanguageIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildUrl(new Client(), '/', ['lang' => 'klingon']);
    }

    public function testInvalidOutputIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->buildUrl(new Client(), '/', ['output' => 'yaml']);
    }

    public function testBulkLookupRefusesEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Client('K'))->bulkLookup([]);
    }

    public function testBulkLookupRefusesMoreThanLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $tooMany = array_fill(0, Client::BULK_LIMIT + 1, '8.8.8.8');
        (new Client('K'))->bulkLookup($tooMany);
    }

    public function testBulkUrlIsCommaSeparated(): void
    {
        $client = new Client('K');
        $url    = $this->buildUrl($client, '/bulk/' . implode(',', ['8.8.8.8', '1.1.1.1']));

        self::assertStringContainsString('/bulk/8.8.8.8,1.1.1.1', $url);
    }

    public function testFluentSettersReturnSelf(): void
    {
        $client = new Client();

        self::assertSame($client, $client->setLanguage('en'));
        self::assertSame($client, $client->setFields(['country']));
        self::assertSame($client, $client->setSecurity(true));
        self::assertSame($client, $client->setRate(false));
        self::assertSame($client, $client->setTimeout(5));
        self::assertSame($client, $client->setConnectTimeout(2));
        self::assertSame($client, $client->setUserAgent('test/1.0'));
    }

    public function testSetLanguageAffectsSubsequentRequests(): void
    {
        $client = (new Client('K'))->setLanguage('de');
        $url    = $this->buildUrl($client, '/');

        self::assertStringContainsString('lang=de', $url);
    }
}
