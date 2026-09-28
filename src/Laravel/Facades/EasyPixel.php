<?php

namespace EasyPixel\Laravel\Facades;

use EasyPixel\Manager;
use Illuminate\Support\Facades\Facade;

/**
 * EasyPixel Laravel Facade.
 *
 * Proxies to the Manager singleton, giving convenient static access to all
 * Manager methods.
 *
 * @see \EasyPixel\Manager
 *
 * @method static \EasyPixel\Client   client()
 * @method static \EasyPixel\Webhook  matrix(string $apiKey)
 * @method static array               sendToMatrices(array $apiKeys, int $sceneId, array $variables = [])
 * @method static array               broadcast(int $sceneId, array $variables = [])
 */
class EasyPixel extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return Manager::class;
    }
}
