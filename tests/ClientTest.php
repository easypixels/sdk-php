<?php

namespace EasyPixel\Tests;

use EasyPixel\Client;
use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private function makeClient($token = 'test-bearer-token')
    {
        $mock   = new MockHttpClient('https://api.easypixel.ru');
        $client = new Client($token, 'https://api.easypixel.ru', $mock);
        return [$client, $mock];
    }

    public function testGetMatricesReturnsArray(): void
    {
        list($client, $mock) = $this->makeClient();

        $mock->addResponse(200, [
            ['id' => 1, 'name' => 'Matrix 1', 'api_key' => 'key1'],
            ['id' => 2, 'name' => 'Matrix 2', 'api_key' => 'key2'],
        ]);

        $result = $client->getMatrices();

        $this->assertCount(2, $result);
        $this->assertSame('Matrix 1', $result[0]['name']);
    }

    public function testGetMatricesCallsCorrectEndpoint(): void
    {
        list($client, $mock) = $this->makeClient();
        $mock->addResponse(200, []);

        $client->getMatrices();

        $request = $mock->getLastRequest();
        $this->assertSame('https://api.easypixel.ru/api/matrices', $request['url']);
        $this->assertSame('GET', $request['method']);
    }

    public function testGetScenariosReturnsArray(): void
    {
        list($client, $mock) = $this->makeClient();

        $mock->addResponse(200, [
            ['id' => 10, 'name' => 'My Scenario'],
        ]);

        $result = $client->getScenarios();

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['id']);
    }

    public function testGetScenesCallsCorrectEndpoint(): void
    {
        list($client, $mock) = $this->makeClient();
        $mock->addResponse(200, []);

        $client->getScenes(15);

        $request = $mock->getLastRequest();
        $this->assertSame('https://api.easypixel.ru/api/scenarios/15/scenes', $request['url']);
    }

    public function testGetVariablesCallsCorrectEndpoint(): void
    {
        list($client, $mock) = $this->makeClient();
        $mock->addResponse(200, []);

        $client->getVariables(20);

        $request = $mock->getLastRequest();
        $this->assertSame('https://api.easypixel.ru/api/scenarios/20/variables', $request['url']);
    }

    public function testPushSceneCallsCorrectEndpointWithPost(): void
    {
        list($client, $mock) = $this->makeClient();
        $mock->addResponse(200, ['message' => 'Scene pushed.']);

        $client->pushScene(3, 42);

        $request = $mock->getLastRequest();
        $this->assertSame('https://api.easypixel.ru/api/matrices/3/scenes/42/push', $request['url']);
        $this->assertSame('POST', $request['method']);
    }

    public function testBearerTokenIsSentInAuthorizationHeader(): void
    {
        list($client, $mock) = $this->makeClient('my-secret-token');
        $mock->addResponse(200, []);

        $client->getMatrices();

        $request = $mock->getLastRequest();
        $headers = $request['headers'];

        $authHeader = null;
        foreach ($headers as $header) {
            if (strpos($header, 'Authorization:') === 0) {
                $authHeader = $header;
                break;
            }
        }

        $this->assertNotNull($authHeader, 'Authorization header not found');
        $this->assertSame('Authorization: Bearer my-secret-token', $authHeader);
    }

    public function testAuthenticationExceptionOnUnauthorized(): void
    {
        $this->expectException(AuthenticationException::class);

        list($client, $mock) = $this->makeClient('bad-token');
        $mock->addResponse(401, ['message' => 'Unauthenticated.']);

        $client->getMatrices();
    }

    public function testNotFoundExceptionOn404(): void
    {
        $this->expectException(NotFoundException::class);

        list($client, $mock) = $this->makeClient();
        $mock->addResponse(404, ['message' => 'Not found.']);

        $client->getScenes(99999);
    }

    public function testValidationExceptionOn422(): void
    {
        $this->expectException(ValidationException::class);

        list($client, $mock) = $this->makeClient();
        $mock->addResponse(422, ['message' => 'No scenario assigned to this matrix.']);

        $client->pushScene(1, 1);
    }

    public function testGetHttpClientReturnsHttpClient(): void
    {
        list($client, $mock) = $this->makeClient();
        $this->assertSame($mock, $client->getHttpClient());
    }
}
