<?php

namespace EasyPixel\Tests;

use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\HttpException;
use EasyPixel\Exception\RateLimitException;
use EasyPixel\HttpClient;
use PHPUnit\Framework\TestCase;

class HttpClientTest extends TestCase
{
    public function testThrottledRequestThrowsRateLimitException(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.'], null, ['retry-after' => '30']);

        try {
            $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame('Too Many Attempts.', $e->getMessage());
            $this->assertSame(30, $e->getRetryAfter());
        }
    }

    public function testRateLimitExceptionKeepsTheExceptionHierarchy(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.']);

        // Manager catches EasyPixelException, so a throttled matrix must not
        // escape a broadcast as an uncaught error.
        $this->expectException(EasyPixelException::class);
        $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
    }

    public function testRateLimitExceptionIsAnHttpException(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.']);

        $this->expectException(HttpException::class);
        $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
    }

    public function testRetryAfterIsNullWhenTheServerDoesNotSendIt(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.']);

        try {
            $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    public function testRetryAfterAcceptsAnHttpDate(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.'], null, [
            'retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() + 60),
        ]);

        try {
            $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertGreaterThan(50, $e->getRetryAfter());
            $this->assertLessThanOrEqual(60, $e->getRetryAfter());
        }
    }

    public function testRetryAfterInThePastClampsToZero(): void
    {
        $mock = new MockHttpClient('https://api.easypixel.ru');
        $mock->addResponse(429, ['message' => 'Too Many Attempts.'], null, [
            'retry-after' => gmdate('D, d M Y H:i:s \G\M\T', time() - 120),
        ]);

        try {
            $mock->post('/api/webhook/somekey', ['scene_id' => 1]);
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertSame(0, $e->getRetryAfter());
        }
    }

    public function testSubclassWithoutResponseHeadersStillWorks(): void
    {
        // executeCurl() gained a 'headers' key; subclasses written against the
        // older three-key shape must keep working.
        $client = new LegacyExecuteCurlClient('https://api.easypixel.ru');

        try {
            $client->post('/api/webhook/somekey', ['scene_id' => 1]);
            $this->fail('Expected RateLimitException was not thrown');
        } catch (RateLimitException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }
}

/**
 * HttpClient subclass whose executeCurl() predates response headers.
 */
class LegacyExecuteCurlClient extends HttpClient
{
    protected function executeCurl($url, array $curlOptions)
    {
        return [
            'body'   => json_encode(['message' => 'Too Many Attempts.']),
            'status' => 429,
            'error'  => null,
        ];
    }
}
