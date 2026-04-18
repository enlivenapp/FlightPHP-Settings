<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings;

use Cycle\ORM\EntityManager;
use Cycle\ORM\ORMInterface;
use Enlivenapp\FlightSettings\Entities\Setting;
use Enlivenapp\FlightSettings\Repositories\SettingRepository;

/**
 * Database-backed settings with in-memory cache.
 *
 * Keys use dot notation: 'Auth.allowRegistration'
 * The part before the dot is the "class" (group), the part after is the "key" (property).
 *
 * Supports contexts for scoped values (e.g., per-user or per-site settings).
 * Falls back from context → general → config defaults.
 */
class Settings
{
    protected ORMInterface $orm;
    protected array $defaults;

    /** @var array In-memory cache: [context][class][key] => [value, type] */
    protected array $cache = [];

    /** @var array Tracks which contexts have been hydrated from DB */
    protected array $hydrated = [];

    public function __construct(ORMInterface $orm, array $defaults = [])
    {
        $this->orm = $orm;
        $this->defaults = $defaults;
    }

    /**
     * Get a setting value.
     *
     * @param string      $key     Dot-notation key: 'Group.property'
     * @param string|null $context Optional context for scoped values
     * @return mixed
     */
    public function get(string $key, ?string $context = null): mixed
    {
        [$class, $property] = $this->parseKey($key);

        // Try contextual first
        if ($context !== null) {
            $this->hydrate($context);

            if (isset($this->cache[$context][$class][$property])) {
                return $this->cache[$context][$class][$property][0];
            }

            // Fall through to general
        }

        // Try general (null context)
        $this->hydrate(null);

        if (isset($this->cache['_general'][$class][$property])) {
            return $this->cache['_general'][$class][$property][0];
        }

        // Fall back to config defaults
        return $this->defaults[$key] ?? $this->defaults[$class . '.' . $property] ?? null;
    }

    /**
     * Set a setting value.
     */
    public function set(string $key, mixed $value = null, ?string $context = null): void
    {
        [$class, $property] = $this->parseKey($key);

        $repo = $this->getRepository();
        $existing = $repo->findSetting($class, $property, $context);

        $now = new \DateTimeImmutable();
        [$preparedValue, $type] = $this->prepareValue($value);

        if ($existing !== null) {
            $existing->value = $preparedValue;
            $existing->type = $type;
            $existing->updated_at = $now;

            $em = new EntityManager($this->orm);
            $em->persist($existing)->run();
        } else {
            $setting = new Setting();
            $setting->class = $class;
            $setting->key = $property;
            $setting->value = $preparedValue;
            $setting->type = $type;
            $setting->context = $context;
            $setting->created_at = $now;
            $setting->updated_at = $now;

            $em = new EntityManager($this->orm);
            $em->persist($setting)->run();
        }

        // Update cache
        $cacheKey = $context ?? '_general';
        $this->cache[$cacheKey][$class][$property] = [$value, $type];
    }

    /**
     * Remove a setting from the database.
     */
    public function forget(string $key, ?string $context = null): void
    {
        [$class, $property] = $this->parseKey($key);

        $repo = $this->getRepository();
        $existing = $repo->findSetting($class, $property, $context);

        if ($existing !== null) {
            $em = new EntityManager($this->orm);
            $em->delete($existing)->run();
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
     * Flush all settings from the database.
     */
    public function flush(): void
    {
        $all = $this->getRepository()->select()->fetchAll();

        $em = new EntityManager($this->orm);
        foreach ($all as $setting) {
            $em->delete($setting);
        }
        $em->run();

        $this->cache = [];
        $this->hydrated = [];
    }

    /**
     * Set config defaults (from plugin Config.php arrays, etc.).
     */
    public function setDefaults(array $defaults): void
    {
        $this->defaults = array_merge($this->defaults, $defaults);
    }

    // -----------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------

    /**
     * Hydrate cache from database for the given context.
     * Loads both general and contextual records in one query.
     */
    protected function hydrate(?string $context): void
    {
        $cacheKey = $context ?? '_general';

        if (in_array($cacheKey, $this->hydrated, true)) {
            return;
        }

        $repo = $this->getRepository();

        if ($context !== null) {
            // Also hydrate general if not done yet
            $this->hydrate(null);

            $records = $repo->findAllByContext($context);
        } else {
            $records = $repo->findAllByContext(null);
        }

        foreach ($records as $setting) {
            $recordContext = $setting->context ?? '_general';
            $value = $this->parseValue($setting->value, $setting->type);
            $this->cache[$recordContext][$setting->class][$setting->key] = [$value, $setting->type];
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

    protected function getRepository(): SettingRepository
    {
        return $this->orm->getRepository(Setting::class);
    }
}
