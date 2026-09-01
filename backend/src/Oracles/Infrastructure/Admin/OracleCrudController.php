<?php

declare(strict_types=1);

namespace App\Oracles\Infrastructure\Admin;

use App\Oracles\Application\Command\CreateOracleCommand;
use App\Oracles\Application\Command\UpdateOracleCommand;
use App\Oracles\Application\CreateOracleHandler;
use App\Oracles\Application\UpdateOracleHandler;
use App\Oracles\Domain\OracleScopeType;
use App\Oracles\Infrastructure\Admin\Form\OracleEntriesCollectionType;
use App\Oracles\Infrastructure\Admin\Form\OracleEntryType;
use App\Oracles\Infrastructure\Persistence\PersistenceOracle;
use App\Shared\Domain\Identifier\GameSystemId;
use App\Shared\Domain\Identifier\OracleId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PersistenceOracle>
 */
final class OracleCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CreateOracleHandler $createHandler,
        private readonly UpdateOracleHandler $updateHandler,
        private readonly Connection $connection,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PersistenceOracle::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield TextField::new('title', 'Table title');

        yield ChoiceField::new('scopeType', 'Visibility')
            ->setChoices([
                'Global — visible to every system' => 'global',
                'One game system' => 'system',
            ])
            ->hideOnIndex();

        yield ChoiceField::new('scopeSystemId', 'Scoped system')
            ->setChoices($this->systemChoices())
            ->setRequired(false)
            ->setHelp('Required for system-scoped tables. Each system owns at most one scoped table.')
            ->hideOnIndex();

        // jsonb payloads are not stringable: EasyAdmin's TextConfigurator
        // throws on list/detail pages before any formatting could run, which
        // is the index crash 08a16c5 fixed for the flow and sheet fields.
        // ArrayField is the array-tolerant concrete type, and the editor is
        // kept off the read pages for the same reason.
        //
        // entry_type is repeated here because ArrayConfigurator injects
        // TextType into any ArrayField that has not set it at field level,
        // which would override the collection type's own default.
        yield ArrayField::new('entries', 'Result entries')
            ->setFormType(OracleEntriesCollectionType::class)
            ->setFormTypeOption('entry_type', OracleEntryType::class)
            ->setHelp('One row per result. Weights are relative likelihoods and must be 1 or more. An empty table is legal — players are told it is empty.')
            ->onlyOnForms();

        yield TextField::new('scopeType', 'Visibility')
            ->formatValue(static fn (mixed $value): string => $value === 'system' ? 'System' : 'Global')
            ->setSortable(true)
            ->onlyOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceOracle);

        try {
            $this->createHandler->handle(new CreateOracleCommand(
                $entityInstance->title(),
                OracleScopeType::from($entityInstance->scopeType()),
                self::scopedSystemId($entityInstance),
                self::entryPayload($entityInstance->entries()),
            ));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('danger', 'That game system already has its own oracle table — each system may own only one scoped table.');
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceOracle);

        try {
            $this->updateHandler->handle(new UpdateOracleCommand(
                OracleId::fromString($entityInstance->id()),
                $entityInstance->title(),
                OracleScopeType::from($entityInstance->scopeType()),
                self::scopedSystemId($entityInstance),
                self::entryPayload($entityInstance->entries()),
            ));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        } catch (UniqueConstraintViolationException) {
            // A failed flush closes the EntityManager; the flash alone tells
            // the author what happened and nothing further is persisted.
            $this->addFlash('danger', 'That game system already has its own oracle table — each system may own only one scoped table.');
        }
    }

    /**
     * Cross-context read via raw SQL: Oracles never imports Rulesets classes.
     *
     * @return array<string, string> label => game_systems.id
     */
    private function systemChoices(): array
    {
        $choices = [];
        foreach ($this->connection->fetchAllAssociative('SELECT id, name FROM game_systems ORDER BY name ASC') as $row) {
            if (is_string($row['id'] ?? null) && is_string($row['name'] ?? null)) {
                $choices[$row['name']] = $row['id'];
            }
        }

        return $choices;
    }

    private static function scopedSystemId(PersistenceOracle $row): ?GameSystemId
    {
        return $row->scopeSystemId() !== null && $row->scopeSystemId() !== ''
            ? GameSystemId::fromString($row->scopeSystemId())
            : null;
    }

    /**
     * @param mixed $entries jsonb payload rows
     * @return list<array{text: string, weight: int}>
     */
    private static function entryPayload(mixed $entries): array
    {
        $payload = [];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (is_array($entry) && is_string($entry['text'] ?? null)) {
                $weight = $entry['weight'] ?? 1;
                $payload[] = [
                    'text' => $entry['text'],
                    'weight' => is_int($weight) ? $weight : (is_numeric($weight) ? (int) $weight : 1),
                ];
            }
        }

        return $payload;
    }
}
