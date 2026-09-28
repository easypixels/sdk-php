<?php

namespace EasyPixel\Tests;

use EasyPixel\HttpClient;

/**
 * Mock HttpClient for testing.
 *
 * Overrides executeCurl() to return pre-queued responses instead of
 * making real HTTP calls. Records all requests for assertion.
 */
class MockHttpClient extends HttpClient
{
    /** @var array[] Queue of responses to return */
    private $responseQueue = [];

    /** @var array[] Log of requests that were made */
    private $requestLog = [];

    /**
     * Queue a response to be returned on the next request.
     *
     * @param int         $statusCode  HTTP status code
     * @param array|null  $body        Response body (will be JSON-encoded)
     * @param string|null $curlError   cURL error message (null = no error)
     * @return $this
     */
    public function addResponse($statusCode, $body = [], $curlError = null)
    {
        $this->responseQueue[] = [
            'status' => $statusCode,
            'body'   => $body !== null ? json_encode($body) : '',
            'error'  => $curlError,
        ];
        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * Each entry contains: ['url', 'method', 'headers', 'body']
     *
     * @return array[]
     */
    public function getRequestLog()
    {
        return $this->requestLog;
    }

    /**
     * Get the last recorded request.
     *
     * @return array|null
     */
    public function getLastRequest()
    {
        return !empty($this->requestLog) ? end($this->requestLog) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCurl($url, array $curlOptions)
    {
        // Parse the method from cURL options
        $method  = isset($curlOptions[CURLOPT_CUSTOMREQUEST]) ? $curlOptions[CURLOPT_CUSTOMREQUEST] : 'GET';
        $headers = isset($curlOptions[CURLOPT_HTTPHEADER]) ? $curlOptions[CURLOPT_HTTPHEADER] : [];
        $body    = isset($curlOptions[CURLOPT_POSTFIELDS]) ? $curlOptions[CURLOPT_POSTFIELDS] : null;

        $this->requestLog[] = [
            'url'     => $url,
            'method'  => $method,
            'headers' => $headers,
            'body'    => $body,
        ];

        if (empty($this->responseQueue)) {
            return [
                'body'   => json_encode(['message' => 'No more queued responses']),
                'status' => 500,
                'error'  => null,
            ];
        }

        return array_shift($this->responseQueue);
    }
}
