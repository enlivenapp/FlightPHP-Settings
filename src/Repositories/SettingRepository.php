<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings\Repositories;

use Cycle\ORM\Select\Repository;
use Enlivenapp\FlightSettings\Entities\Setting;

/**
 * @extends Repository<Setting>
 */
class SettingRepository extends Repository
{
    public function findSetting(string $class, string $key, ?string $context = null): ?Setting
    {
        $query = $this->select()
            ->where('class', $class)
            ->where('key', $key);

        if ($context === null) {
            $query->where('context', null);
        } else {
            $query->where('context', $context);
        }

        return $query->fetchOne();
    }

    public function findAllByClass(string $class, ?string $context = null): array
    {
        $query = $this->select()->where('class', $class);

        if ($context === null) {
            $query->where('context', null);
        } else {
            // Fetch both general and contextual in one query
            $query->where(function ($q) use ($context) {
                $q->where('context', null)->orWhere('context', $context);
            });
        }

        return $query->fetchAll();
    }

    public function findAllByContext(?string $context = null): array
    {
        $query = $this->select();

        if ($context === null) {
            $query->where('context', null);
        } else {
            $query->where(function ($q) use ($context) {
                $q->where('context', null)->orWhere('context', $context);
            });
        }

        return $query->fetchAll();
    }
}
