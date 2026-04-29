<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings\Models;

/**
 * Setting ActiveRecord model.
 *
 * Represents a single row in the `settings` table.
 */
class Setting extends \flight\ActiveRecord
{
    /**
     * @param mixed $pdo    PDO connection (or any value flight-AR accepts)
     * @param array $config Optional AR config overrides
     */
    public function __construct($pdo = null, array $config = [])
    {
        parent::__construct($pdo, 'settings', $config);
    }

    /**
     * Find a single Setting row by class/key/context.
     *
     * @return self|null hydrated instance or null when no match
     */
    public function findOneBy(string $class, string $key, ?string $context): ?self
    {
        $this->reset();

        $this->eq('class', $class)->eq('key', $key);

        if ($context === null) {
            $this->isNull('context');
        } else {
            $this->eq('context', $context);
        }

        $this->find();

        return $this->isHydrated() ? $this : null;
    }

    /**
     * Find all Setting rows for a given context.
     *
     * - $context === null: only rows with NULL context.
     * - $context !== null: rows with NULL context OR matching context (one query).
     *
     * @return array<int, self>
     */
    public function findByContext(?string $context): array
    {
        $this->reset();

        if ($context === null) {
            return $this->isNull('context')->findAll();
        }

        return $this->query(
            'SELECT * FROM settings WHERE context IS NULL OR context = ?',
            [$context]
        );
    }
}
