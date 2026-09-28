<?php

namespace EasyPixel;

/**
 * EasyPixel API client using Sanctum Bearer token authentication.
 *
 * Provides access to the full authenticated API for reading matrices,
 * scenarios, scenes and variables, as well as pushing scenes directly.
 */
class Client
{
    /** @var HttpClient */
    protected $httpClient;

    /** @var string */
    protected $token;

    /**
     * @param string          $token      Sanctum Bearer token
     * @param string          $baseUrl    API base URL
     * @param HttpClient|null $httpClient Optional pre-configured HttpClient (for testing)
     */
    public function __construct($token, $baseUrl = 'https://api.easypixel.ru', $httpClient = null)
    {
        $this->token = $token;

        if ($httpClient !== null) {
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = new HttpClient($baseUrl, [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ]);
        }
    }

    /**
     * Get all matrices belonging to the authenticated user.
     *
     * @return array
     */
    public function getMatrices()
    {
        return $this->httpClient->get('/api/matrices');
    }

    /**
     * Get all scenarios belonging to the authenticated user.
     *
     * @return array
     */
    public function getScenarios()
    {
        return $this->httpClient->get('/api/scenarios');
    }

    /**
     * Get all scenes in a scenario.
     *
     * @param int $scenarioId
     * @return array
     */
    public function getScenes($scenarioId)
    {
        return $this->httpClient->get('/api/scenarios/' . $scenarioId . '/scenes');
    }

    /**
     * Get all scene variables in a scenario.
     *
     * @param int $scenarioId
     * @return array
     */
    public function getVariables($scenarioId)
    {
        return $this->httpClient->get('/api/scenarios/' . $scenarioId . '/variables');
    }

    /**
     * Push a scene to a matrix immediately via the authenticated API.
     *
     * The scene must belong to the scenario assigned to the matrix.
     *
     * @param int $matrixId
     * @param int $sceneId
     * @return array
     */
    public function pushScene($matrixId, $sceneId)
    {
        return $this->httpClient->post('/api/matrices/' . $matrixId . '/scenes/' . $sceneId . '/push');
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
