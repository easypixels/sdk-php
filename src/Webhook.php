<?php

namespace EasyPixel;

/**
 * EasyPixel Webhook client using matrix API key authentication.
 *
 * Allows external systems to trigger scene display on a specific matrix
 * without a full Bearer token. The API key is embedded in the URL.
 *
 * Example:
 *   $webhook = new Webhook('your-matrix-api-key');
 *   $webhook->send(42, ['count' => '15', 'status' => 'open']);
 */
class Webhook
{
    /** @var HttpClient */
    protected $httpClient;

    /** @var string */
    protected $apiKey;

    /**
     * @param string          $apiKey     Matrix API key (32-char hex string)
     * @param string          $baseUrl    API base URL
     * @param HttpClient|null $httpClient Optional pre-configured HttpClient (for testing)
     */
    public function __construct($apiKey, $baseUrl = 'https://api.easypixel.ru', $httpClient = null)
    {
        $this->apiKey = $apiKey;

        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = new HttpClient($baseUrl, [
                'Accept' => 'application/json',
            ]);
        }
    }

    /**
     * Create a new Webhook instance.
     *
     * @param string $apiKey  Matrix API key
     * @param string $baseUrl API base URL
     * @return static
     */
    public static function create($apiKey, $baseUrl = 'https://api.easypixel.ru')
    {
        return new static($apiKey, $baseUrl);
    }

    /**
     * Send a scene to the matrix.
     *
     * Variables update the scene's dynamic placeholders before rendering.
     * Returns 202 Accepted — the actual display happens asynchronously.
     *
     * @param int   $sceneId   ID of the scene to display (must belong to the matrix's assigned scenario)
     * @param array $variables Associative array of variable values ['name' => 'value'].
     *                         Scalar values (int/float/bool) are cast to strings automatically.
     * @return array Response: ['message', 'scene_id', 'matrix_id', 'variables_updated']
     *
     * @throws \EasyPixel\Exception\NotFoundException   If the API key is invalid (404)
     * @throws \EasyPixel\Exception\ValidationException If no scenario assigned or scene not found (422)
     * @throws \EasyPixel\Exception\EasyPixelException  On network or other errors
     */
    public function send($sceneId, array $variables = [])
    {
        $data = ['scene_id' => (int) $sceneId];

        if (!empty($variables)) {
            // The API validates variables.* as strings; cast so callers can pass ints/floats/bools.
            $data['variables'] = array_map('strval', $variables);
        }

        return $this->httpClient->post('/api/webhook/' . $this->apiKey, $data);
    }

    /**
     * Get the matrix API key used by this webhook.
     *
     * @return string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Get the underlying HttpClient instance.
     *
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }
}
