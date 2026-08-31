<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Persistence;

use Doctrine\ORM\Mapping as ORM;

/**
 * Persistence model for the Oracles context. The scope discriminator lives
 * in two columns (`scope_type`, `scope_system_id`) guarded by a partial
 * unique index created in the migration (see data-model.md persistence
 * notes) — entries rest in a jsonb column.
 *
 * @phpstan-import-type OracleEntryPayload from OracleJsonMapper
 */
#[ORM\Entity]
#[ORM\Table(name: 'oracles')]
#[ORM\Index(name: 'idx_oracles_scope', columns: ['scope_type', 'scope_system_id'])]
class PersistenceOracle
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 160)]
    private string $title;

    #[ORM\Column(type: 'string', length: 16)]
    private string $scopeType = 'global';

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $scopeSystemId = null;

    /**
     * @var list<OracleEntryPayload>
     */
    #[ORM\Column(type: 'jsonb')]
    private array $entries = [];

    /**
     * All arguments are optional so EasyAdmin can bind forms onto a blank
     * instance (`new PersistenceOracle()`); the repository always supplies
     * the full snapshot, and admin writes go through the application handlers.
     *
     * @param list<OracleEntryPayload> $entries
     */
    public function __construct(
        string $id = '',
        string $title = '',
        string $scopeType = 'global',
        ?string $scopeSystemId = null,
        array $entries = [],
    ) {
        $this->id = $id;
        $this->replace($title, $scopeType, $scopeSystemId, $entries);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function scopeType(): string
    {
        return $this->scopeType;
    }

    public function scopeSystemId(): ?string
    {
        return $this->scopeSystemId;
    }

    /** @return list<OracleEntryPayload> */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Applies a domain snapshot.
     *
     * @param list<OracleEntryPayload> $entries
     */
    public function replace(string $title, string $scopeType, ?string $scopeSystemId, array $entries): void
    {
        $this->title = $title;
        $this->scopeType = $scopeType;
        $this->scopeSystemId = $scopeSystemId;
        $this->entries = $entries;
    }

    /*
     * Field mutators for the ORM/form adapter boundary only — see the same
     * note on PersistenceGameSystem (A6). `entries` deliberately has none:
     * the admin form does not map it yet (A4).
     */

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setScopeType(string $scopeType): void
    {
        $this->scopeType = $scopeType;
    }

    public function setScopeSystemId(?string $scopeSystemId): void
    {
        $this->scopeSystemId = $scopeSystemId;
    }
}
