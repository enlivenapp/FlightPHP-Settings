<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings\Database\Migrations;

use Enlivenapp\Migrations\Services\Migration;

class CreateSettingsTable extends Migration
{
    public function up(): void
    {
        $this->table('settings')
            ->addColumn('id', 'primary')
            ->addColumn('class', 'string', ['length' => 255])
            ->addColumn('key', 'string', ['length' => 255])
            ->addColumn('value', 'text', ['nullable' => true])
            ->addColumn('type', 'string', ['length' => 31, 'default' => 'string'])
            ->addColumn('title', 'string', ['length' => 30, 'nullable' => true])
            ->addColumn('description', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('context', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => true])
            ->addColumn('updated_at', 'datetime', ['nullable' => true])
            ->addIndex(['class', 'key', 'context'], ['name' => 'class_key_context'])
            ->create();
    }

    public function down(): void
    {
        $this->table('settings')->drop();
    }
}
