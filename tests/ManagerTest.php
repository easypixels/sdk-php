<?php

namespace EasyPixel\Tests;

use EasyPixel\Client;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Manager;
use EasyPixel\Webhook;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{
    /**
     * Create a Manager with a mock HttpClient injected into its Client.
     *
     * @return array{Manager, MockHttpClient}
     */
    private function makeManager()
    {
        $mock    = new MockHttpClient('https://api.easypixel.ru');
        $client  = new Client('test-token', 'https://api.easypixel.ru', $mock);
        $manager = new Manager('test-token', 'https://api.easypixel.ru');

        // Replace the internal client with our mock-backed one
        $reflection = new \ReflectionProperty(Manager::class, 'client');
        $reflection->setAccessible(true);
        $reflection->setValue($manager, $client);

        return [$manager, $mock];
    }

    public function testClientReturnsClientInstance(): void
    {
        list($manager,) = $this->makeManager();
        $this->assertInstanceOf(Client::class, $manager->client());
    }

    public function testMatrixReturnsWebhookInstance(): void
    {
        list($manager,) = $this->makeManager();
        $webhook = $manager->matrix('key123');
        $this->assertInstanceOf(Webhook::class, $webhook);
        $this->assertSame('key123', $webhook->getApiKey());
    }

    public function testMatrixCachesInstances(): void
    {
        list($manager,) = $this->makeManager();

        $first  = $manager->matrix('key-abc');
        $second = $manager->matrix('key-abc');

        $this->assertSame($first, $second);
    }

    public function testMatrixDifferentKeysDifferentInstances(): void
    {
        list($manager,) = $this->makeManager();

        $first  = $manager->matrix('key-1');
        $second = $manager->matrix('key-2');

        $this->assertNotSame($first, $second);
    }

    public function testSendToMatricesCollectsResults(): void
    {
        list($manager,) = $this->makeManager();

        // Prepare mock webhooks for two keys
        $mock1 = new MockHttpClient('https://api.easypixel.ru');
        $mock1->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 5, 'matrix_id' => 1, 'variables_updated' => []]);

        $mock2 = new MockHttpClient('https://api.easypixel.ru');
        $mock2->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 5, 'matrix_id' => 2, 'variables_updated' => []]);

        // Inject pre-configured Webhook instances
        $webhook1 = new Webhook('api-key-1', 'https://api.easypixel.ru', $mock1);
        $webhook2 = new Webhook('api-key-2', 'https://api.easypixel.ru', $mock2);

        $reflection = new \ReflectionProperty(Manager::class, 'webhooks');
        $reflection->setAccessible(true);
        $reflection->setValue($manager, [
            'api-key-1' => $webhook1,
            'api-key-2' => $webhook2,
        ]);

        $results = $manager->sendToMatrices(['api-key-1', 'api-key-2'], 5, ['count' => '10']);

        $this->assertArrayHasKey('api-key-1', $results);
        $this->assertArrayHasKey('api-key-2', $results);
        $this->assertSame('Webhook accepted.', $results['api-key-1']['message']);
        $this->assertSame('Webhook accepted.', $results['api-key-2']['message']);
    }

    public function testSendToMatricesHandlesErrorsGracefully(): void
    {
        list($manager,) = $this->makeManager();

        $mockOk   = new MockHttpClient('https://api.easypixel.ru');
        $mockOk->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 1, 'matrix_id' => 1, 'variables_updated' => []]);

        $mockFail = new MockHttpClient('https://api.easypixel.ru');
        $mockFail->addResponse(404, ['message' => 'Invalid API key.']);

        $webhookOk   = new Webhook('good-key', 'https://api.easypixel.ru', $mockOk);
        $webhookFail = new Webhook('bad-key', 'https://api.easypixel.ru', $mockFail);

        $reflection = new \ReflectionProperty(Manager::class, 'webhooks');
        $reflection->setAccessible(true);
        $reflection->setValue($manager, [
            'good-key' => $webhookOk,
            'bad-key'  => $webhookFail,
        ]);

        $results = $manager->sendToMatrices(['good-key', 'bad-key'], 1);

        $this->assertSame('Webhook accepted.', $results['good-key']['message']);
        $this->assertTrue($results['bad-key']['error']);
        $this->assertSame('Invalid API key.', $results['bad-key']['message']);
    }

    public function testBroadcastFetchesMatricesAndSendsToAll(): void
    {
        list($manager, $clientMock) = $this->makeManager();

        // Queue the getMatrices() call
        $clientMock->addResponse(200, [
            ['id' => 1, 'name' => 'Matrix A', 'api_key' => 'key-a'],
            ['id' => 2, 'name' => 'Matrix B', 'api_key' => 'key-b'],
        ]);

        // Queue webhook responses for each matrix
        $mockA = new MockHttpClient('https://api.easypixel.ru');
        $mockA->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 3, 'matrix_id' => 1, 'variables_updated' => []]);

        $mockB = new MockHttpClient('https://api.easypixel.ru');
        $mockB->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 3, 'matrix_id' => 2, 'variables_updated' => []]);

        $webhookA = new Webhook('key-a', 'https://api.easypixel.ru', $mockA);
        $webhookB = new Webhook('key-b', 'https://api.easypixel.ru', $mockB);

        $reflection = new \ReflectionProperty(Manager::class, 'webhooks');
        $reflection->setAccessible(true);
        $reflection->setValue($manager, [
            'key-a' => $webhookA,
            'key-b' => $webhookB,
        ]);

        $results = $manager->broadcast(3, ['text' => 'Hello']);

        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
        $this->assertSame('Webhook accepted.', $results[1]['message']);
        $this->assertSame('Webhook accepted.', $results[2]['message']);
    }

    public function testBroadcastSkipsMatricesWithoutApiKey(): void
    {
        list($manager, $clientMock) = $this->makeManager();

        $clientMock->addResponse(200, [
            ['id' => 1, 'name' => 'No Key Matrix', 'api_key' => ''],
            ['id' => 2, 'name' => 'Has Key Matrix', 'api_key' => 'valid-key'],
        ]);

        $mockValid = new MockHttpClient('https://api.easypixel.ru');
        $mockValid->addResponse(202, ['message' => 'Webhook accepted.', 'scene_id' => 1, 'matrix_id' => 2, 'variables_updated' => []]);

        $webhookValid = new Webhook('valid-key', 'https://api.easypixel.ru', $mockValid);

        $reflection = new \ReflectionProperty(Manager::class, 'webhooks');
        $reflection->setAccessible(true);
        $reflection->setValue($manager, ['valid-key' => $webhookValid]);

        $results = $manager->broadcast(1);

        $this->assertArrayNotHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
    }
}
