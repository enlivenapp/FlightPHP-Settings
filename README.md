[![Version](http://poser.pugx.org/enlivenapp/flight-settings/version)](https://packagist.org/packages/enlivenapp/flight-settings)
[![License](http://poser.pugx.org/enlivenapp/flight-settings/license)](https://packagist.org/packages/enlivenapp/flight-settings)
[![Suggesters](http://poser.pugx.org/enlivenapp/flight-settings/suggesters)](https://packagist.org/packages/enlivenapp/flight-settings)
[![PHP Version Require](http://poser.pugx.org/enlivenapp/flight-settings/require/php)](https://packagist.org/packages/enlivenapp/flight-settings)
[![Monthly Downloads](https://poser.pugx.org/enlivenapp/flight-settings/d/monthly)](https://packagist.org/packages/enlivenapp/flight-settings)

# flight-settings

A settings store for [FlightPHP](https://flightphp.com), built as a [Flight School](https://github.com/enlivenapp/FlightPHP-Flight-School) plugin. Values live in the database, get cached for the request, and keep their PHP types — write an int, read an int back.

## What you get

- Simple `get()` / `set()` / `forget()` API on `Flight::settings()`.
- Keys like `'Site.name'` or `'Auth.allowRegistration'`, grouped by the part preceeding the dot.
- Per-user, per-tenant, or any other scope — pass a `context` string.
- One query per scope per request. Everything after that comes from memory.

## Requirements

- PHP 8.1+
- `flightphp/core` ^3.0
- `enlivenapp/flight-school` ^0.2
- `enlivenapp/migrations`
- `flightphp/active-record`

## Install

```bash
composer require enlivenapp/flight-settings
```

Enable it in `app/config/config.php`:

```php
'plugins' => [
    'enlivenapp/flight-settings' => [
        'enabled'  => true,
        'priority' => 20,
    ],
],
```

That's it. On the next page load, enlivenapp/migrations creates the `settings` table and seeds `CMS.siteName` and `CMS.siteByline` as baseline rows.

## Quick start

```php
$settings = Flight::settings();

$name = $settings->get('CMS.siteName'); //null
$name = $settings->get('CMS.siteByline'); //null

$settings->set('CMS.siteName', 'My App');
$settings->set('CMS.siteByline', 'Do Great Things!');

$name = $settings->get('CMS.siteName'); // 'My App'
$name = $settings->get('CMS.siteByline'); // Do Great Things!
```

`get()` returns `null` when the key isn't in the database.

## Keys

Dot notation: the part before the dot is the group, the part after is the property. Both are required — a key without a dot throws `InvalidArgumentException`.

```php
$settings->get('Auth.allowRegistration'); // group = Auth, property = allowRegistration
$settings->get('Mail.fromAddress');
```

## Contexts

A context lets the same setting hold different values for different things. You pick any string that identifies what the setting belongs to, and pass it as the `context` argument. Examples of what a context could be:

- A user ID → this setting belongs to user `42`
- A site code (if one app serves multiple sites) → this setting is for site `westcoast`
- A customer or organization ID → this setting belongs to `ACME Corp`
- A language code → this setting is for the Spanish version

Every row in the `settings` table has an optional `context` column. Writing with a context ties the row to that string; reading with the same string returns that row. Reading with no context returns the general (site-wide) row.

```php
$settings->set('User.theme', 'dark', context: (string) $userId);
$settings->get('User.theme', context: (string) $userId);
$settings->forget('User.theme', context: (string) $userId);
```

`get()` with a context returns only that context's row, or `null` if there isn't one.

## Types

A database column doesn't remember PHP types. Save `true` and you usually read back `"1"`. Save `20` and you read back `"20"`. This plugin records the type next to the value so the cast is done for you on read — what you put in is what you get back.

```php
$settings->set('Site.itemsPerPage', 20);
$settings->get('Site.itemsPerPage'); // 20 — integer, not "20"

$settings->set('Auth.allowRegistration', true);
$settings->get('Auth.allowRegistration'); // true — boolean, not "1"
```

Supported: `string`, `integer`, `double` (float), `boolean`, `array`, `object`, `NULL`. Arrays and objects are stored via PHP's `serialize()` and restored with `unserialize()` — so you can stash small structured values, just don't treat it like a document store.

## API

All methods on `Flight::settings()`:

| Method | Signature | What it does |
|---|---|---|
| `get` | `get(string $key, ?string $context = null): mixed` | Read. Returns `null` if not in the database. |
| `set` | `set(string $key, mixed $value = null, ?string $context = null): void` | Write. Cache updates immediately. |
| `has` | `has(string $key, ?string $context = null): bool` | Does a database row exist? |
| `forget` | `forget(string $key, ?string $context = null): void` | Delete one row. |

## Schema

The migration creates a `settings` table:

| Column | Type | Notes |
|---|---|---|
| `id` | int | Primary key |
| `class` | varchar(255) | Part before the dot |
| `key` | varchar(255) | Part after the dot |
| `value` | text, nullable | Serialized value |
| `type` | varchar(31) | PHP type, used for cast-back |
| `context` | varchar(255), nullable | Scope string; `NULL` = general |
| `created_at` | datetime, nullable | |
| `updated_at` | datetime, nullable | |

Composite index on `(class, key, context)`.

## License

MIT — see [LICENSE](LICENSE).
