<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings;

use Enlivenapp\FlightSchool\PluginInterface;
use Enlivenapp\FlightSettings\Services\Settings;
use flight\Engine;
use flight\net\Router;
use Flight;

class Plugin implements PluginInterface
{
    public function register(Engine $app, Router $router, array $config = []): void
    {
        $instance = new Settings(Flight::db());
        $app->map('settings', function () use ($instance) {
            return $instance;
        });
    }
}
