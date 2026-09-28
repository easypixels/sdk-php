<?php

namespace EasyPixel\Exception;

/**
 * Thrown when the API returns 429 (Too Many Requests).
 *
 * The webhook endpoint is throttled twice: 120 requests per minute per IP
 * address and 60 per minute per matrix API key. Serving several matrices from
 * one host hits the IP limit first.
 */
class RateLimitException extends HttpException
{
    /** @var int|null Seconds to wait before retrying, from the Retry-After header */
    protected $retryAfter;

    /**
     * @param string          $message
     * @param int             $statusCode
     * @param \Throwable|null $previous
     * @param array|null      $response
     * @param int|null        $retryAfter
     */
    public function __construct($message = '', $statusCode = 429, $previous = null, $response = null, $retryAfter = null)
    {
        parent::__construct($message, $statusCode, $previous, $response);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Seconds to wait before retrying, or null if the server did not say.
     *
     * @return int|null
     */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }
}
