<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EasyPixel Bearer Token
    |--------------------------------------------------------------------------
    |
    | Your Sanctum Bearer token from the EasyPixel web application.
    | Required for reading matrices, scenarios, scenes and variables.
    |
    */
    'token' => env('EASYPIXEL_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | EasyPixel API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the EasyPixel API. Change this for self-hosted instances.
    |
    */
    'base_url' => env('EASYPIXEL_URL', 'https://api.easypixel.ru'),
];
