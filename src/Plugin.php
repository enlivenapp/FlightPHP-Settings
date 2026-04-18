<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings;

use Enlivenapp\FlightSchool\PluginInterface;
use flight\Engine;
use flight\net\Router;

class Plugin implements PluginInterface
{
    public function register(Engine $app, Router $router, array $config = []): void
    {
        // Register entity directory for schema discovery
        $database = $app->get('database');
        if ($database) {
            $database->addEntityDirectory(__DIR__ . '/Entities');
        }

        // Register the Settings service
        $app->map('settings', function () use ($app, $config) {
            static $instance = null;
            if ($instance === null) {
                $instance = new Settings($app->orm(), $config['defaults'] ?? []);
            }
            return $instance;
        });
    }
}
