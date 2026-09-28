<?php

namespace EasyPixel;

use EasyPixel\Exception\EasyPixelException;

/**
 * EasyPixel Manager — convenience class for working with multiple matrices.
 *
 * Combines the authenticated Client (for reading data) with per-matrix
 * Webhook instances (for sending scenes). Ideal when managing 10+ matrices.
 *
 * Example:
 *   $manager = new Manager('your-bearer-token');
 *
 *   // Send to one matrix
 *   $manager->matrix('api-key-1')->send(42, ['count' => '5']);
 *
 *   // Send to multiple specific matrices
 *   $results = $manager->sendToMatrices(['key1', 'key2', 'key3'], 42, ['count' => '5']);
 *
 *   // Broadcast to ALL matrices of the user
 *   $results = $manager->broadcast(42, ['count' => '5']);
 */
class Manager
{
    /** @var Client */
    protected $client;

    /** @var string */
    protected $baseUrl;

    /** @var Webhook[] */
    protected $webhooks = [];

    /**
     * @param string $token   Sanctum Bearer token
     * @param string $baseUrl API base URL
     */
    public function __construct($token, $baseUrl = 'https://api.easypixel.ru')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client  = new Client($token, $this->baseUrl);
    }

    /**
     * Get the authenticated API client.
     *
     * @return Client
     */
    public function client()
    {
        return $this->client;
    }

    /**
     * Get a Webhook instance for a specific matrix by its API key.
     *
     * Instances are cached — calling this method multiple times with the same
     * key returns the same object.
     *
     * @param string $apiKey Matrix API key
     * @return Webhook
     */
    public function matrix($apiKey)
    {
        if (!isset($this->webhooks[$apiKey])) {
            $this->webhooks[$apiKey] = new Webhook($apiKey, $this->baseUrl);
        }
        return $this->webhooks[$apiKey];
    }

    /**
     * Send a scene to a specific list of matrices by their API keys.
     *
     * Errors per matrix are caught and returned in the results array rather
     * than stopping the whole broadcast.
     *
     * @param string[] $apiKeys  List of matrix API keys
     * @param int      $sceneId  Scene ID to display
     * @param array    $variables Variable values to update
     * @return array Results keyed by API key. Each value is either the success
     *               response array or ['error' => true, 'message' => '...']
     */
    public function sendToMatrices(array $apiKeys, $sceneId, array $variables = [])
    {
        $results = [];

        foreach ($apiKeys as $apiKey) {
            try {
                $results[$apiKey] = $this->matrix($apiKey)->send($sceneId, $variables);
            } catch (EasyPixelException $e) {
                $results[$apiKey] = [
                    'error'   => true,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Broadcast a scene to ALL matrices belonging to the authenticated user.
     *
     * Fetches the matrix list via the API, then sends the webhook to each one.
     * Errors per matrix are caught and included in the results.
     *
     * @param int   $sceneId   Scene ID to display
     * @param array $variables Variable values to update
     * @return array Results keyed by matrix ID. Each value is either the success
     *               response array or ['error' => true, 'message' => '...']
     */
    public function broadcast($sceneId, array $variables = [])
    {
        $matrices = $this->client->getMatrices();
        $results  = [];

        foreach ($matrices as $matrix) {
            if (!isset($matrix['api_key']) || $matrix['api_key'] === '') {
                continue;
            }

            $matrixId = isset($matrix['id']) ? $matrix['id'] : $matrix['api_key'];
            $apiKey   = $matrix['api_key'];

            try {
                $results[$matrixId] = $this->matrix($apiKey)->send($sceneId, $variables);
            } catch (EasyPixelException $e) {
                $results[$matrixId] = [
                    'error'   => true,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
