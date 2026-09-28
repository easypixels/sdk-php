<?php

namespace EasyPixel\Exception;

class HttpException extends EasyPixelException
{
    /** @var int */
    protected $statusCode;

    /**
     * @param string          $message
     * @param int             $statusCode
     * @param \Throwable|null $previous
     * @param array|null      $response
     */
    public function __construct($message = '', $statusCode = 0, $previous = null, $response = null)
    {
        parent::__construct($message, $statusCode, $previous, $response);
        $this->statusCode = $statusCode;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }
}
