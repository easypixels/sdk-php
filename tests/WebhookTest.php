<?php

namespace EasyPixel\Tests;

use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\ValidationException;
use EasyPixel\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    private function makeWebhook($apiKey = 'testapikey123')
    {
        $mock    = new MockHttpClient('https://api.easypixel.ru');
        $webhook = new Webhook($apiKey, 'https://api.easypixel.ru', $mock);
        return [$webhook, $mock];
    }

    public function testSendReturnsSuccessResponse(): void
    {
        list($webhook, $mock) = $this->makeWebhook();

        $mock->addResponse(202, [
            'message'           => 'Webhook accepted.',
            'scene_id'          => 42,
            'matrix_id'         => 7,
            'variables_updated' => [],
        ]);

        $result = $webhook->send(42);

        $this->assertSame('Webhook accepted.', $result['message']);
        $this->assertSame(42, $result['scene_id']);
        $this->assertSame(7, $result['matrix_id']);
    }

    public function testSendPostsToCorrectUrl(): void
    {
        list($webhook, $mock) = $this->makeWebhook('myapikey456');

        $mock->addResponse(202, [
            'message' => 'Webhook accepted.',
            'scene_id' => 1,
            'matrix_id' => 1,
            'variables_updated' => [],
        ]);

        $webhook->send(1);

        $request = $mock->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('https://api.easypixel.ru/api/webhook/myapikey456', $request['url']);
        $this->assertSame('POST', $request['method']);
    }

    public function testSendIncludesSceneIdInBody(): void
    {
        list($webhook, $mock) = $this->makeWebhook();

        $mock->addResponse(202, [
            'message' => 'Webhook accepted.',
            'scene_id' => 99,
            'matrix_id' => 1,
            'variables_updated' => [],
        ]);

        $webhook->send(99);

        $request = $mock->getLastRequest();
        $body    = json_decode($request['body'], true);

        $this->assertSame(99, $body['scene_id']);
        $this->assertArrayNotHasKey('variables', $body);
    }

    public function testSendWithVariablesIncludesThemInBody(): void
    {
        list($webhook, $mock) = $this->makeWebhook();

        $mock->addResponse(202, [
            'message'           => 'Webhook accepted.',
            'scene_id'          => 5,
            'matrix_id'         => 2,
            'variables_updated' => ['count', 'status'],
        ]);

        $webhook->send(5, ['count' => '42', 'status' => 'open']);

        $request = $mock->getLastRequest();
        $body    = json_decode($request['body'], true);

        $this->assertSame(5, $body['scene_id']);
        $this->assertSame(['count' => '42', 'status' => 'open'], $body['variables']);
    }

    public function testSendCastsNonStringVariablesToStrings(): void
    {
        list($webhook, $mock) = $this->makeWebhook();

        $mock->addResponse(202, [
            'message'           => 'Webhook accepted.',
            'scene_id'          => 5,
            'matrix_id'         => 2,
            'variables_updated' => ['count', 'temperature', 'active'],
        ]);

        $webhook->send(5, ['count' => 42, 'temperature' => 22.5, 'active' => true]);

        $request = $mock->getLastRequest();
        $body    = json_decode($request['body'], true);

        // The API validates variables.* as strings, so everything is cast before sending.
        $this->assertSame(['count' => '42', 'temperature' => '22.5', 'active' => '1'], $body['variables']);
    }

    public function testSendWithEmptyVariablesOmitsVariablesKey(): void
    {
        list($webhook, $mock) = $this->makeWebhook();

        $mock->addResponse(202, [
            'message' => 'Webhook accepted.',
            'scene_id' => 1,
            'matrix_id' => 1,
            'variables_updated' => [],
        ]);

        $webhook->send(1, []);

        $request = $mock->getLastRequest();
        $body    = json_decode($request['body'], true);

        $this->assertArrayNotHasKey('variables', $body);
    }

    public function testInvalidApiKeyThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);

        list($webhook, $mock) = $this->makeWebhook('invalidkey');
        $mock->addResponse(404, ['message' => 'Invalid API key.']);

        $webhook->send(1);
    }

    public function testNoScenarioAssignedThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        list($webhook, $mock) = $this->makeWebhook();
        $mock->addResponse(422, ['message' => 'No scenario assigned to this matrix.']);

        $webhook->send(1);
    }

    public function testSceneNotInScenarioThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        list($webhook, $mock) = $this->makeWebhook();
        $mock->addResponse(422, ['message' => 'Scene not found in assigned scenario.']);

        $webhook->send(999);
    }

    public function testGetApiKeyReturnsKey(): void
    {
        list($webhook,) = $this->makeWebhook('specifickey789');
        $this->assertSame('specifickey789', $webhook->getApiKey());
    }

    public function testStaticCreateReturnsInstance(): void
    {
        $webhook = Webhook::create('testkey', 'https://api.easypixel.ru');
        $this->assertInstanceOf(Webhook::class, $webhook);
        $this->assertSame('testkey', $webhook->getApiKey());
    }

    public function testValidationExceptionCarriesMessage(): void
    {
        list($webhook, $mock) = $this->makeWebhook();
        $mock->addResponse(422, ['message' => 'No scenario assigned to this matrix.']);

        try {
            $webhook->send(1);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertSame('No scenario assigned to this matrix.', $e->getMessage());
            $this->assertSame(422, $e->getStatusCode());
        }
    }
}
