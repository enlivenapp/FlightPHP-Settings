<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings\Services;

use Enlivenapp\FlightSettings\Models\Setting;
use flight\database\PdoWrapper;
use Flight;

/**
 * Database-backed settings with in-memory cache.
 *
 * Keys use dot notation: 'Auth.allowRegistration'
 * The part before the dot is the "class" (group), the part after is the "key" (property).
 *
 * Supports contexts for scoped values (e.g., per-user or per-site settings).
 * A read with a context returns only that context's row; no fallback to the general row.
 *
 * On construction, hydrates general-context settings and pushes them into
 * Flight::set() so they are available globally via Flight::get('Class.key').
 */
class Settings
{
    protected PdoWrapper $pdo;

    /** @var array In-memory cache: [context][class][key] => [value, type] */
    protected array $cache = [];

    /** @var array Tracks which contexts have been hydrated from DB */
    protected array $hydrated = [];

    public function __construct(PdoWrapper $pdo)
    {
        $this->pdo = $pdo;
        $this->hydrate(null);
    }

    /**
     * Get a setting value.
     *
     * @param string      $key     Dot-notation key: 'Group.property'
     * @param string|null $context Optional context for scoped values
     */
    public function get(string $key, ?string $context = null): mixed
    {
        [$class, $property] = $this->parseKey($key);

        if ($context !== null) {
            $this->hydrate($context);
            return $this->cache[$context][$class][$property][0] ?? null;
        }

        $this->hydrate(null);
        return $this->cache['_general'][$class][$property][0] ?? null;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value = null, ?string $context = null): void
    {
        [$class, $property] = $this->parseKey($key);

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        [$preparedValue, $type] = $this->prepareValue($value);

        $existing = $this->setting()->findOneBy($class, $property, $context);

        if ($existing !== null) {
            $existing->value = $preparedValue;
            $existing->type = $type;
            $existing->updated_at = $now;
            $existing->save();
        } else {
            $new = $this->setting();
            $new->class = $class;
            $new->key = $property;
            $new->value = $preparedValue;
            $new->type = $type;
            $new->context = $context;
            $new->created_at = $now;
            $new->updated_at = $now;
            $new->insert();
        }

        // Update cache
        $cacheKey = $context ?? '_general';
        $this->cache[$cacheKey][$class][$property] = [$value, $type];
    }

    /**
     * Save all key/value pairs for a class at once.
     *
     * @param string      $class   Class name (e.g. 'CMS')
     * @param array       $data    key => value pairs to save
     * @param string|null $context Optional context
     */
    public function saveClass(string $class, array $data, ?string $context = null): void
    {
        $existing = $this->getClass($class, $context);

        foreach ($data as $key => $value) {
            if (isset($existing[$key])) {
                $value = $this->castToType($value, $existing[$key]['type']);
            }
            $this->set("{$class}.{$key}", $value, $context);
        }
    }

    /**
     * Cast a value to the given type.
     *
     * @param mixed  $value Raw value (typically a string from POST)
     * @param string $type  Target type (string, integer, double, boolean)
     */
    protected function castToType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'double'  => (float) $value,
            default   => (string) $value,
        };
    }

    /**
     * Remove a setting from the database.
     */
    public function forget(string $key, ?string $context = null): void
    {
        [$class, $property] = $this->parseKey($key);

        $existing = $this->setting()->findOneBy($class, $property, $context);

        if ($existing !== null) {
            $existing->delete();
        }

        // Remove from cache
        $cacheKey = $context ?? '_general';
        unset($this->cache[$cacheKey][$class][$property]);
    }

    /**
     * Check if a setting exists in the database.
     */
    public function has(string $key, ?string $context = null): bool
    {
        [$class, $property] = $this->parseKey($key);

        $this->hydrate($context);

        $cacheKey = $context ?? '_general';

        return isset($this->cache[$cacheKey][$class][$property]);
    }

    /**
     * Get all distinct class names.
     *
     * @param string|null $context Optional context
     * @return string[] Sorted class names
     */
    public function getClasses(?string $context = null): array
    {
        $this->hydrate($context);
        $cacheKey = $context ?? '_general';
        $classes = array_keys($this->cache[$cacheKey] ?? []);
        sort($classes);
        return $classes;
    }

    /**
     * Get all settings grouped by class.
     *
     * @param string|null $context Optional context
     * @return array<string, array<string, mixed>> class => [key => value]
     */
    public function getAll(?string $context = null): array
    {
        $this->hydrate($context);
        $cacheKey = $context ?? '_general';
        $entries = $this->cache[$cacheKey] ?? [];

        $result = [];
        foreach ($entries as $class => $keys) {
            foreach ($keys as $key => $pair) {
                $result[$class][$key] = $pair[0];
            }
        }

        ksort($result);
        return $result;
    }

    /**
     * Get all key/value/type entries for a given class.
     *
     * @param string      $class   Class name (e.g. 'CMS')
     * @param string|null $context Optional context
     * @return array<string, array{value: mixed, type: string, title: ?string, description: ?string}>
     */
    public function getClass(string $class, ?string $context = null): array
    {
        $this->hydrate($context);
        $cacheKey = $context ?? '_general';
        $entries = $this->cache[$cacheKey][$class] ?? [];

        $result = [];
        foreach ($entries as $key => $pair) {
            $result[$key] = [
                'value'       => $pair[0],
                'type'        => $pair[1],
                'title'       => $pair[2] ?? null,
                'description' => $pair[3] ?? null,
            ];
        }

        return $result;
    }

    // -----------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------

    /**
     * Hydrate cache from database for the given context.
     * Loads both general and contextual records in one query when context is non-null.
     * When hydrating general context, also pushes values into Flight::set().
     */
    protected function hydrate(?string $context): void
    {
        $cacheKey = $context ?? '_general';

        if (in_array($cacheKey, $this->hydrated, true)) {
            return;
        }

        if ($context !== null) {
            // Also hydrate general if not done yet
            $this->hydrate(null);
        }

        $records = $this->setting()->findByContext($context);

        foreach ($records as $row) {
            $recordContext = $row->context ?? '_general';
            $value = $this->parseValue($row->value, $row->type);
            $this->cache[$recordContext][$row->class][$row->key] = [
                $value,
                $row->type,
                $row->title ?? null,
                $row->description ?? null,
            ];

            // Push general-context settings into Flight::set()
            if ($recordContext === '_general') {
                Flight::set("{$row->class}.{$row->key}", $value);
            }
        }

        $this->hydrated[] = $cacheKey;
    }

    /**
     * Parse a dot-notation key into [class, property].
     *
     * @return array{0: string, 1: string}
     */
    protected function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Setting key must use dot notation: '{$key}'");
        }

        return $parts;
    }

    /**
     * Prepare a value for database storage.
     *
     * @return array{0: ?string, 1: string} [preparedValue, phpType]
     */
    protected function prepareValue(mixed $value): array
    {
        $type = gettype($value);

        if ($value === null) {
            return [null, 'NULL'];
        }

        if (is_bool($value)) {
            return [$value ? '1' : '0', 'boolean'];
        }

        if (is_array($value) || is_object($value)) {
            return [serialize($value), $type];
        }

        return [(string) $value, $type];
    }

    /**
     * Parse a stored value back to its PHP type.
     */
    protected function parseValue(?string $value, string $type): mixed
    {
        if ($type === 'NULL' || $value === null) {
            return null;
        }

        if ($type === 'boolean') {
            return $value === '1';
        }

        if (in_array($type, ['array', 'object'], true)) {
            $unserialized = @unserialize($value);
            return $unserialized !== false ? $unserialized : $value;
        }

        if ($type === 'integer') {
            return (int) $value;
        }

        if ($type === 'double') {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Fresh Setting AR instance — avoids query-state bleed between calls.
     */
    private function setting(): Setting
    {
        return new Setting($this->pdo);
    }
}
