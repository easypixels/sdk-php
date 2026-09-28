<?php

namespace EasyPixel\Exception;

use RuntimeException;

class EasyPixelException extends RuntimeException
{
    /** @var array|null */
    protected $response;

    /**
     * @param string          $message
     * @param int             $code
     * @param \Throwable|null $previous
     * @param array|null      $response
     */
    public function __construct($message = '', $code = 0, $previous = null, $response = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Get the decoded API response body, if available.
     *
     * @return array|null
     */
    public function getResponse()
    {
        return $this->response;
    }
}
