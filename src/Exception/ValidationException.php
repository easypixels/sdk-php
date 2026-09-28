<?php

namespace EasyPixel\Exception;

/**
 * Thrown when the API returns 422 (Unprocessable Entity).
 * For webhooks this means: no scenario assigned, or scene not found in scenario.
 */
class ValidationException extends HttpException
{
    /**
     * Get validation error messages from the response, if any.
     *
     * @return array
     */
    public function getErrors()
    {
        if ($this->response !== null && isset($this->response['errors'])) {
            return $this->response['errors'];
        }
        return [];
    }
}
