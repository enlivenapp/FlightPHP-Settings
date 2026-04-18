<?php

/**
 * @package   Enlivenapp\FlightSettings
 * @copyright 2026 enlivenapp
 * @license   MIT
 */

declare(strict_types=1);

namespace Enlivenapp\FlightSettings\Entities;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Annotated\Annotation\Table\Index;
use Enlivenapp\FlightSettings\Repositories\SettingRepository;

#[Entity(table: 'settings', repository: SettingRepository::class)]
#[Index(columns: ['class', 'key', 'context'])]
class Setting
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'string')]
    public string $class;

    #[Column(type: 'string', name: 'key')]
    public string $key;

    #[Column(type: 'text', nullable: true)]
    public ?string $value = null;

    #[Column(type: 'string', length: 31, default: 'string')]
    public string $type = 'string';

    #[Column(type: 'string', nullable: true)]
    public ?string $context = null;

    #[Column(type: 'datetime', nullable: true, typecast: 'datetime')]
    public ?\DateTimeImmutable $created_at = null;

    #[Column(type: 'datetime', nullable: true, typecast: 'datetime')]
    public ?\DateTimeImmutable $updated_at = null;
}
