# EasyPixel PHP SDK

PHP SDK for the [EasyPixel](https://easypixel.ru) LED matrix management API.

[![Tests](https://github.com/easypixel/sdk-php/actions/workflows/tests.yml/badge.svg)](https://github.com/easypixel/sdk-php/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.0-8892BF.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Requirements

- PHP 7.0 or higher
- PHP extensions: `curl`, `json`

## Installation

```bash
composer require easypixel/sdk-php
```

## Quick Start

```php
use EasyPixel\Manager;

$manager = new Manager('your-bearer-token');

// Send a scene to one specific matrix
$manager->matrix('matrix-api-key')->send(42);

// Broadcast to all your matrices at once
$manager->broadcast(42, ['count' => '15', 'status' => 'open']);
```

---

## Authentication

EasyPixel uses two types of credentials:

| Credential | Where to get | Used for |
|---|---|---|
| **Bearer token** | EasyPixel web app → Profile → API Token | Reading data, pushing scenes via authenticated API |
| **Matrix API key** | EasyPixel web app → Matrix settings | Sending webhooks to a specific matrix |

---

## Classes

### `EasyPixel\Manager` — recommended entry point

The Manager combines the authenticated Client with per-matrix webhook senders. Best choice when working with multiple matrices.

```php
use EasyPixel\Manager;

$manager = new Manager(
    token:   'your-bearer-token',
    baseUrl: 'https://api.easypixel.ru'  // optional, this is the default
);

// Access the authenticated API client
$matrices = $manager->client()->getMatrices();

// Get a Webhook for a specific matrix (instances are cached)
$webhook = $manager->matrix('matrix-api-key-here');
$webhook->send(42, ['temperature' => '22.5']);

// Send to a specific set of matrices
$results = $manager->sendToMatrices(
    ['api-key-1', 'api-key-2', 'api-key-3'],
    sceneId:   42,
    variables: ['count' => '5']
);

// Broadcast to ALL matrices of the account
$results = $manager->broadcast(42, ['status' => 'open']);
```

`sendToMatrices()` and `broadcast()` return an array of results. Errors per matrix are caught and returned inline — one failed matrix does not stop the rest:

```php
foreach ($results as $matrixId => $result) {
    if (isset($result['error']) && $result['error']) {
        echo "Matrix {$matrixId} failed: {$result['message']}\n";
    } else {
        echo "Matrix {$matrixId}: scene {$result['scene_id']} sent\n";
    }
}
```

---

### `EasyPixel\Webhook` — simple webhook sender

Use this when you only need to send scenes and don't need to read API data.

```php
use EasyPixel\Webhook;

// Using constructor
$webhook = new Webhook('matrix-api-key');
$result  = $webhook->send(42);

// Using static factory
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

**Important:** The webhook returns `202 Accepted` immediately. The scene is rendered and sent to the hardware asynchronously. There is no delivery confirmation.

---

### `EasyPixel\Client` — full API client

Use this to read data from the EasyPixel API. Requires a Bearer token.

```php
use EasyPixel\Client;

$client = new Client('your-bearer-token');

// List all matrices
$matrices = $client->getMatrices();
// [
//     ['id' => 1, 'name' => 'Entrance', 'api_key' => 'abc123...', 'webhook_url' => '...', ...],
//     ...
// ]

// List all scenarios
$scenarios = $client->getScenarios();

// List scenes in a scenario
$scenes = $client->getScenes(scenarioId: 10);
// [
//     ['id' => 42, 'name' => 'Welcome', 'type' => 'dynamic', 'is_default' => true, ...],
//     ...
// ]

// List scene variables in a scenario
$variables = $client->getVariables(scenarioId: 10);
// [
//     ['id' => 1, 'name' => 'count', 'source' => 'webhook', 'resolved_value' => '5', ...],
//     ...
// ]

// Push a scene via the authenticated API (synchronous, requires scene to be in assigned scenario)
$result = $client->pushScene(matrixId: 1, sceneId: 42);
```

---

## Error Handling

All SDK methods throw exceptions from the `EasyPixel\Exception` namespace:

```php
use EasyPixel\Exception\EasyPixelException;
use EasyPixel\Exception\AuthenticationException;
use EasyPixel\Exception\NotFoundException;
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

} catch (AuthenticationException $e) {
    // 401 / 403: Invalid or expired Bearer token
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
    └── ValidationException      (422)
```

---

## Laravel Integration

### Setup

1. Install the package:
   ```bash
   composer require easypixel/sdk-php
   ```

2. The ServiceProvider is auto-discovered in Laravel 5.5+. No manual registration needed.

3. Add your credentials to `.env`:
   ```dotenv
   EASYPIXEL_TOKEN=your-sanctum-bearer-token
   EASYPIXEL_URL=https://api.easypixel.ru
   ```

4. Optionally publish the config file:
   ```bash
   php artisan vendor:publish --tag=easypixel
   ```
   This creates `config/easypixel.php`.

### Using the Facade

```php
use EasyPixel\Laravel\Facades\EasyPixel;

// Send a scene to one matrix
EasyPixel::matrix('matrix-api-key')->send(42, ['count' => '10']);

// Broadcast to all matrices
$results = EasyPixel::broadcast(42, ['status' => 'open']);

// Read data via the client
$matrices  = EasyPixel::client()->getMatrices();
$scenarios = EasyPixel::client()->getScenarios();
$scenes    = EasyPixel::client()->getScenes(10);
```

### Using Dependency Injection

```php
use EasyPixel\Manager;
use EasyPixel\Client;

class MatrixController extends Controller
{
    public function __construct(
        private Manager $manager,
        private Client  $client,
    ) {}

    public function notify(): JsonResponse
    {
        $results = $this->manager->broadcast(42, ['count' => (string) Order::pending()->count()]);
        return response()->json($results);
    }
}
```

---

## Working with Multiple Matrices

Typical workflow when managing many matrices:

```php
use EasyPixel\Manager;

$manager = new Manager('your-bearer-token');

// Step 1: List all matrices and find the scene IDs you need
$matrices  = $manager->client()->getMatrices();
$scenarios = $manager->client()->getScenarios();

// Step 2: Find scenes for a scenario
$scenes = $manager->client()->getScenes($scenarios[0]['id']);
$sceneId = $scenes[0]['id'];

// Step 3: Broadcast to all matrices with current variable values
$results = $manager->broadcast($sceneId, [
    'free_spots' => (string) getParkingFreeSpots(),
    'status'     => getStatusLabel(),
]);

// Or send to a subset
$apiKeys = array_column($matrices, 'api_key');
$subset  = array_slice($apiKeys, 0, 5);
$results = $manager->sendToMatrices($subset, $sceneId, ['count' => '42']);
```

---

## Custom Base URL

For self-hosted EasyPixel instances:

```php
$manager = new Manager('your-token', 'https://easypixel.yourcompany.com');
$webhook = new Webhook('matrix-key', 'https://easypixel.yourcompany.com');
$client  = new Client('your-token', 'https://easypixel.yourcompany.com');
```

In Laravel, set `EASYPIXEL_URL` in `.env`.

---

## License

MIT © EasyPixel
