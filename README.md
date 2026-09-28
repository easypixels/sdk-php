# EasyPixel PHP SDK

PHP SDK for sending scenes to [EasyPixel](https://easypixel.ru) LED matrices.

[![Tests](https://github.com/easypixels/sdk-php/actions/workflows/tests.yml/badge.svg)](https://github.com/easypixels/sdk-php/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.2-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Requirements

- PHP 7.2 or higher
- PHP extensions: `curl`, `json`

## Installation

Add the repository and require the package:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/easypixels/sdk-php.git" }
    ],
    "require": {
        "easypixels/sdk-php": "^1.0"
    }
}
```

```bash
composer update easypixels/sdk-php
```

## Quick Start

```php
use EasyPixel\Webhook;

$webhook = new Webhook('matrix-api-key');

// Show scene 42 on that matrix, filling in two of its variables
$webhook->send(42, ['count' => '15', 'status' => 'open']);
```

---

## Authentication

The SDK uses one credential: the **matrix API key**, a 32-character hex string you copy from
the EasyPixel web app in the settings of that matrix. It is scoped to a single matrix and can
do exactly one thing — show a scene from the scenario assigned to it.

The key travels in the URL (`POST /api/webhook/{api_key}`), so treat it like a password: keep
it out of the repository and out of client-side code. If it leaks, issue a new one with
`POST /api/matrices/{matrix}/regenerate-api-key`; the old key stops working immediately.

---

## Rate Limits

The webhook endpoint is throttled twice, and both limits apply at once:

| Limit | Scope |
|---|---|
| 120 requests / minute | per client IP address |
| 60 requests / minute | per matrix API key |

Driving several matrices from one host hits the IP limit first: 60 requests per key sounds
generous until three matrices share one server's address. Over either limit the API answers
`429` and the SDK throws `RateLimitException`, whose `getRetryAfter()` carries the
`Retry-After` header in seconds (`null` if the server did not send one). Schedule the retry
from that value.

---

## Sending scenes

```php
use EasyPixel\Webhook;

// Using the constructor
$webhook = new Webhook('matrix-api-key');
$result  = $webhook->send(42);

// Using the static factory
$webhook = Webhook::create('matrix-api-key');
$result  = $webhook->send(42, ['count' => '100', 'label' => 'Free spots']);

// Response: 202 Accepted
// [
//     'message'           => 'Webhook accepted.',
//     'scene_id'          => 42,
//     'matrix_id'         => 7,
//     'variables_updated' => ['count', 'label'],
// ]
```

The scene must belong to the scenario currently assigned to that matrix, otherwise the API
answers `422`.

**Important:** the webhook returns `202 Accepted` immediately. The scene is rendered and sent
to the hardware asynchronously. There is no delivery confirmation.

`variables_updated` lists only the variables the matrix's scenario actually defines — unknown
names are ignored without an error, so compare that list against what you sent if a value never
shows up on the panel.

Values are cast with `strval()`, so integers and floats can be passed as they are. Beware of
`false`, which becomes an empty string.

---

## Error Handling

All SDK methods throw exceptions from the `EasyPixel\Exception` namespace:

```php
use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\NotFoundException;
use EasyPixel\Exception\RateLimitException;
use EasyPixel\Exception\ValidationException;
use EasyPixel\Exception\HttpException;

try {
    $webhook->send(42, ['count' => '5']);
} catch (NotFoundException $e) {
    // 404: Invalid API key
    echo "Invalid matrix API key: " . $e->getMessage();

} catch (ValidationException $e) {
    // 422: No scenario assigned, or scene not found in scenario
    echo "API error: " . $e->getMessage();
    // $e->getErrors() returns validation error details if available

} catch (RateLimitException $e) {
    // 429: throttled — see "Rate Limits" above.
    // getRetryAfter() is null when the server sent no Retry-After header.
    echo "Throttled, retry in " . $e->getRetryAfter() . "s";

} catch (AuthenticationException $e) {
    // 401 / 403
    echo "Authentication failed: " . $e->getMessage();

} catch (HttpException $e) {
    // Other HTTP errors (500, etc.)
    echo "HTTP {$e->getStatusCode()}: {$e->getMessage()}";

} catch (EasyPixelException $e) {
    // Network errors, cURL failures, etc.
    echo "Request failed: " . $e->getMessage();
}
```

Exception hierarchy:
```
EasyPixelException (RuntimeException)
└── HttpException
    ├── AuthenticationException  (401, 403)
    ├── NotFoundException        (404)
    ├── ValidationException      (422)
    └── RateLimitException       (429)
```

---

## Working with Multiple Matrices

Keep the API keys in your own configuration and walk them, so one failing matrix does not stop
the rest:

```php
use EasyPixel\Webhook;
use EasyPixel\Exception\RateLimitException;
use EasyPixel\Exception\EasyPixelException;

$apiKeys = config('parking.easypixel_keys'); // ['entrance' => 'abc…', 'exit' => 'def…']
$sceneId = 42;
$variables = ['free_spots' => (string) getParkingFreeSpots()];

foreach ($apiKeys as $name => $apiKey) {
    try {
        Webhook::create($apiKey)->send($sceneId, $variables);
        echo "{$name}: sent\n";
    } catch (RateLimitException $e) {
        echo "{$name}: throttled, retry in {$e->getRetryAfter()}s\n";
    } catch (EasyPixelException $e) {
        echo "{$name}: {$e->getMessage()}\n";
    }
}
```

Each `Webhook` carries its own `HttpClient`, so keep one instance per matrix and reuse it.

---

## Timeouts

The request timeout defaults to 30 seconds and the connection timeout to 10. Both are set on
the underlying HTTP client:

```php
$webhook->getHttpClient()->setTimeout(5)->setConnectTimeout(2);
```

---

## License

MIT © EasyPixel
