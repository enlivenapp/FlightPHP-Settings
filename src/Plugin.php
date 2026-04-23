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
use Flight;

class Plugin implements PluginInterface
{
 
     public function register(Engine $app, Router $router, array $config = []): void
    {
        // Map the settings service (singleton via static $instance)
        $app->map('settings', function () {
            static $instance = null;
            if ($instance === null) {
                $instance = new Settings(Flight::db());
            }
            return $instance;
        });

        // Eager-boot: only run if settings migration has been recorded
        $this->bootSettings();
    }

    protected function bootSettings(): void
    {
        try {
            $pdo = Flight::db();
            $stmt = $pdo->prepare(
                "SELECT 1 FROM migrations WHERE package = ? AND class = ? LIMIT 1"
            );
            $stmt->execute([
                'enlivenapp/flight-settings',
                'CreateSettingsTable',
            ]);
            if ($stmt->fetchColumn() === false) {
                return; // migration not yet applied
            }
        } catch (\Throwable $e) {
            return; // migrations table itself may be missing; stay quiet
        }

        // Hydrate general-context settings into Flight::app()
        $settings = Flight::settings();
        $stmt = $pdo->query("SELECT class, `key`, value, type FROM settings WHERE context IS NULL");
        foreach ($stmt as $row) {
            // Route through Settings::get() so cache + type-parsing stay consistent
            $key = $row['class'] . '.' . $row['key'];
            Flight::set($key, $settings->get($key));
        }
    }
}
