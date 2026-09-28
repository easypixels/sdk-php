<?php

namespace EasyPixel;

use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\HttpException;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\RateLimitException;
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
     * Add or replace a header sent with every request.
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function setDefaultHeader($name, $value)
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
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

        // Subclasses may override executeCurl() without returning headers.
        $responseHeaders = isset($result['headers']) ? $result['headers'] : [];

        if ($body !== '' && $body !== false) {
            $decoded = json_decode($body, true);
        }

        if ($decoded === null) {
            $decoded = [];
        }

        if ($status >= 400) {
            $message = isset($decoded['message']) ? $decoded['message'] : 'HTTP Error ' . $status;
            $this->throwForStatus($status, $message, $decoded, $responseHeaders);
        }

        return $decoded;
    }

    /**
     * Execute the cURL request. Override this method in subclasses for testing.
     *
     * @param string $url
     * @param array  $curlOptions
     * @return array{body: string|false, status: int, error: string|null, headers: array}
     */
    protected function executeCurl($url, array $curlOptions)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, $curlOptions);

        $responseHeaders = [];
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $line) use (&$responseHeaders) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return strlen($line);
        });

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_errno($ch) ? curl_error($ch) : null;

        curl_close($ch);

        return [
            'body'    => $body,
            'status'  => $status,
            'error'   => $error,
            'headers' => $responseHeaders,
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
     * @param array  $headers Response headers, keys lowercased
     * @return void
     *
     * @throws HttpException
     */
    protected function throwForStatus($status, $message, array $response, array $headers = [])
    {
        switch ($status) {
            case 401:
            case 403:
                throw new AuthenticationException($message, $status, null, $response);
            case 404:
                throw new NotFoundException($message, $status, null, $response);
            case 422:
                throw new ValidationException($message, $status, null, $response);
            case 429:
                throw new RateLimitException(
                    $message,
                    $status,
                    null,
                    $response,
                    $this->parseRetryAfter($headers)
                );
            default:
                throw new HttpException($message, $status, null, $response);
        }
    }

    /**
     * Read Retry-After as a number of seconds.
     *
     * RFC 7231 allows either a delay in seconds or an HTTP date; Laravel's
     * throttle middleware sends seconds, but a proxy in front of it may not.
     *
     * @param array $headers Response headers, keys lowercased
     * @return int|null
     */
    protected function parseRetryAfter(array $headers)
    {
        if (!isset($headers['retry-after'])) {
            return null;
        }

        $value = trim($headers['retry-after']);

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }
}
