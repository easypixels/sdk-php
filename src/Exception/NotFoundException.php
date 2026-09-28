<?php

namespace EasyPixel\Exception;

/**
 * Thrown when the API returns 404 (Not Found).
 * For webhooks this typically means an invalid API key.
 */
class NotFoundException extends HttpException
{
}
