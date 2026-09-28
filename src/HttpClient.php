<?php

namespace EasyPixel;

use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\HttpException;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\ValidationException;

class HttpClient
{
    /** @var string */
    protected $baseUrl;

    /** @var array */
    protected $defaultHeaders;

    /** @var int */
    protected $timeout;

    /** @var int */
    protected $connectTimeout;

    /**
     * @param string $baseUrl
     * @param array  $defaultHeaders Associative array: ['Header-Name' => 'value']
     * @param int    $timeout        Request timeout in seconds
     * @param int    $connectTimeout Connection timeout in seconds
     */
    public function __construct($baseUrl, array $defaultHeaders = [], $timeout = 30, $connectTimeout = 10)
    {
        $this->baseUrl        = rtrim($baseUrl, '/');
        $this->defaultHeaders = $defaultHeaders;
        $this->timeout        = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    /**
     * Send a GET request.
     *
     * @param string $path
     * @param array  $headers Extra headers (associative)
     * @return array
     */
    public function get($path, array $headers = [])
    {
        return $this->request('GET', $path, ['headers' => $headers]);
    }

    /**
     * Send a POST request with a JSON body.
     *
     * @param string $path
     * @param array  $data    Request body data
     * @param array  $headers Extra headers (associative)
     * @return array
     */
    public function post($path, array $data = [], array $headers = [])
    {
        return $this->request('POST', $path, ['headers' => $headers, 'body' => $data]);
    }

    /**
     * Set request timeout.
     *
     * @param int $seconds
     * @return $this
     */
    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set connection timeout.
     *
     * @param int $seconds
     * @return $this
     */
    public function setConnectTimeout($seconds)
    {
        $this->connectTimeout = $seconds;
        return $this;
    }

    /**
     * Execute an HTTP request.
     *
     * @param string $method
     * @param string $path
     * @param array  $options Keys: 'headers' (array), 'body' (array)
     * @return array
     *
     * @throws EasyPixelException
     */
    public function request($method, $path, array $options = [])
    {
        $url     = $this->baseUrl . $path;
        $headers = array_merge($this->defaultHeaders, isset($options['headers']) ? $options['headers'] : []);

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_FOLLOWLOCATION => false,
        ];

        if (isset($options['body'])) {
            $json                           = json_encode($options['body']);
            $curlOptions[CURLOPT_POSTFIELDS] = $json;
            $headers['Content-Type']         = 'application/json';
            $headers['Content-Length']        = (string) strlen($json);
        }

        $curlOptions[CURLOPT_HTTPHEADER] = $this->formatHeaders($headers);

        $result = $this->executeCurl($url, $curlOptions);

        if ($result['error'] !== null) {
            throw new EasyPixelException('cURL error: ' . $result['error']);
        }

        $status  = (int) $result['status'];
        $body    = $result['body'];
        $decoded = null;

        if ($body !== '' && $body !== false) {
            $decoded = json_decode($body, true);
        }

        if ($decoded === null) {
            $decoded = [];
        }

        if ($status >= 400) {
            $message = isset($decoded['message']) ? $decoded['message'] : 'HTTP Error ' . $status;
            $this->throwForStatus($status, $message, $decoded);
        }

        return $decoded;
    }

    /**
     * Execute the cURL request. Override this method in subclasses for testing.
     *
     * @param string $url
     * @param array  $curlOptions
     * @return array{body: string|false, status: int, error: string|null}
     */
    protected function executeCurl($url, array $curlOptions)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $curlOptions);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_errno($ch) ? curl_error($ch) : null;

        curl_close($ch);

        return [
            'body'   => $body,
            'status' => $status,
            'error'  => $error,
        ];
    }

    /**
     * Format associative headers array to indexed cURL format.
     *
     * @param array $headers
     * @return array
     */
    protected function formatHeaders(array $headers)
    {
        $formatted = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $formatted[] = $value;
            } else {
                $formatted[] = $key . ': ' . $value;
            }
        }
        return $formatted;
    }

    /**
     * Throw the appropriate exception for an HTTP status code.
     *
     * @param int    $status
     * @param string $message
     * @param array  $response
     * @return void
     *
     * @throws HttpException
     */
    protected function throwForStatus($status, $message, array $response)
    {
        switch ($status) {
            case 401:
            case 403:
                throw new AuthenticationException($message, $status, null, $response);
            case 404:
                throw new NotFoundException($message, $status, null, $response);
            case 422:
                throw new ValidationException($message, $status, null, $response);
            default:
                throw new HttpException($message, $status, null, $response);
        }
    }
}
