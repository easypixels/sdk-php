<?php

namespace EasyPixel\Tests;

use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\HttpException;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testEasyPixelExceptionStoresResponse(): void
    {
        $response  = ['message' => 'error', 'details' => 'something'];
        $exception = new EasyPixelException('Test error', 0, null, $response);

        $this->assertSame('Test error', $exception->getMessage());
        $this->assertSame($response, $exception->getResponse());
    }

    public function testEasyPixelExceptionWithNullResponse(): void
    {
        $exception = new EasyPixelException('Test error');

        $this->assertNull($exception->getResponse());
    }

    public function testHttpExceptionStoresStatusCode(): void
    {
        $exception = new HttpException('Not found', 404, null, ['message' => 'Not found']);

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Not found', $exception->getMessage());
    }

    public function testHttpExceptionExtendsEasyPixelException(): void
    {
        $exception = new HttpException('Error', 500);

        $this->assertInstanceOf(EasyPixelException::class, $exception);
    }

    public function testAuthenticationExceptionExtendsHttpException(): void
    {
        $exception = new AuthenticationException('Unauthorized', 401);

        $this->assertInstanceOf(HttpException::class, $exception);
        $this->assertInstanceOf(EasyPixelException::class, $exception);
        $this->assertSame(401, $exception->getStatusCode());
    }

    public function testNotFoundExceptionExtendsHttpException(): void
    {
        $exception = new NotFoundException('Invalid API key.', 404);

        $this->assertInstanceOf(HttpException::class, $exception);
        $this->assertSame(404, $exception->getStatusCode());
    }

    public function testValidationExceptionExtendsHttpException(): void
    {
        $exception = new ValidationException('No scenario assigned.', 422);

        $this->assertInstanceOf(HttpException::class, $exception);
        $this->assertSame(422, $exception->getStatusCode());
    }

    public function testValidationExceptionGetErrorsReturnsEmptyArrayWhenNoErrors(): void
    {
        $exception = new ValidationException('No scenario assigned.', 422, null, ['message' => 'No scenario assigned.']);

        $this->assertSame([], $exception->getErrors());
    }

    public function testValidationExceptionGetErrorsReturnsErrorsFromResponse(): void
    {
        $errors   = ['scene_id' => ['The scene_id field is required.']];
        $response = ['message' => 'Validation error', 'errors' => $errors];
        $exception = new ValidationException('Validation error', 422, null, $response);

        $this->assertSame($errors, $exception->getErrors());
    }

    public function testValidationExceptionGetErrorsWithNullResponse(): void
    {
        $exception = new ValidationException('Error', 422);

        $this->assertSame([], $exception->getErrors());
    }
}
