# flight-settings

Database-backed key-value settings for [FlightPHP](https://flightphp.com), built for the [Flight School](https://github.com/enlivenapp/FlightPHP-Flight-School) plugin system. Ported from the CodeIgniter Settings library.

Settings are stored in the database and cached in memory for the duration of the request. They support dot-notation keys, optional scoping by context (e.g. per-user or per-site), and automatic type preservation for strings, integers, floats, booleans, arrays, and objects.

## Requirements

- PHP 8.1+
- `flightphp/core` ^3.0
- `enlivenapp/flight-school` ^0.2
- Cycle ORM (provided through flight-school's database layer)

## Installation

```bash
composer require enlivenapp/flight-settings
```

Run migrations to create the `settings` table:

```bash
php runway cycle:migrate
```

## Key Format

Keys use dot notation: `'Group.property'`. The part before the dot is the group (maps to the `class` column in the database), the part after is the property (maps to the `key` column). Both parts are required.

```php
$settings->get('Auth.allowRegistration');  // group: Auth, property: allowRegistration
$settings->get('Mail.fromAddress');        // group: Mail, property: fromAddress
```

## Contexts

A context scopes a setting to a specific entity — for example a user ID or a site slug. When you retrieve a contextual setting, the library checks in this order:

1. The contextual value (e.g. this specific user's preference)
2. The general value (no context — the site-wide default stored in the database)
3. Config defaults (hardcoded fallbacks from `Config.php` or `setDefaults()`)

```php
// Store a per-user preference
$settings->set('User.theme', 'dark', context: (string) $userId);

// Retrieve it — falls back to general, then config defaults
$theme = $settings->get('User.theme', context: (string) $userId);
```

If you don't need scoping, just omit the context parameter and settings work as simple key-value pairs.

## How It Works

1. On the first `get()` or `set()` call for a context, the plugin loads all rows for that context from the database in a single query and caches them in memory for the remainder of the request.
2. Subsequent reads for the same context are served from the in-memory cache — no additional queries.
3. Values are stored as strings in the database alongside a PHP type name (`boolean`, `integer`, `double`, `array`, `object`, `NULL`). On retrieval they are cast back to their original type automatically.
4. Arrays and objects are stored via `serialize()` / `unserialize()`.

## Default Values

Defaults are registered through the plugin config and are used as a fallback when no database row exists. They are never written to the database — they just provide a value when nothing else is found.

```php
// src/Config/Config.php
return [
    'defaults' => [
        'Auth.allowRegistration' => true,
        'Site.itemsPerPage'      => 20,
    ],
];
```

Plugins can also push their own defaults at runtime via `setDefaults()` in their `Plugin::register()` method. See [Flight School](https://github.com/enlivenapp/FlightPHP-Flight-School) for plugin development.

## Usage

```php
$settings = Flight::settings();

// Get a value (returns null if not found and no default is set)
$value = $settings->get('Auth.allowRegistration');

// Set a value (creates or updates the setting)
$settings->set('Auth.allowRegistration', true);
$settings->set('Auth.siteByline', 'Do Great Things!');

// Check if a setting exists in the database (does not check defaults)
if ($settings->has('Site.logo')) { ... }

// Delete a setting from the database
$settings->forget('Site.logo');

// Delete all settings from the database
$settings->flush();
```

Values can be any type — strings, integers, floats, booleans, arrays, objects, or null. The type is preserved automatically.

## License

MIT
