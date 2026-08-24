<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Application\Command\UpdateFlowDefinitionCommand;
use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\UpdateFlowDefinitionHandler;
use App\Rulesets\Application\UpdateSheetStructureHandler;
use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\GameSystemStatus;
use App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PersistenceGameSystem>
 */
final class SystemCrudController extends AbstractCrudController
{
    private const SUPERSEDED_MESSAGE = 'Your changes were superseded — another edit was saved first. Review the current version below and apply your changes again.';

    public function __construct(
        private readonly UpdateFlowDefinitionHandler $flowHandler,
        private readonly UpdateSheetStructureHandler $sheetHandler,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PersistenceGameSystem::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('name')->add('status');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);
        yield TextField::new('name', 'System name');

        yield TextField::new('description', 'Description')->hideOnIndex();

        yield ChoiceField::new('status', 'Availability')
            ->setChoices(array_combine(
                array_map(static fn (GameSystemStatus $s): string => ucfirst($s->value), GameSystemStatus::cases()),
                GameSystemStatus::cases(),
            ));

        // jsonb arrays are not stringable: EasyAdmin's TextConfigurator throws
        // on list/detail pages before formatValue could ever run, so these two
        // fields are form-only (index/detail render the profile columns).
        yield TextareaField::new('flowDefinition', 'Campaign flow (JSON)')
            ->setHelp('{"stages":[{"name":"Scene","guidance":"…"}],"starting_stage":"Scene","transitions":[]}')
            ->setFormType(JsonDocumentType::class)
            ->setFormTypeOption(JsonDocumentType::OPTION_IS_SHEET, false)
            ->onlyOnForms();

        yield TextareaField::new('sheetStructure', 'Character sheet structure (JSON)')
            ->setHelp('Optional. {"fields":[{"key":"name","label":"Name","type":"text","required_for_pc":true,"required_for_npc":true}],"version":1}')
            ->setFormType(JsonDocumentType::class)
            ->setFormTypeOption(JsonDocumentType::OPTION_IS_SHEET, true)
            ->onlyOnForms();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceGameSystem);

        // New systems have no campaigns, so plain VO validation suffices.
        $starting = $entityInstance->flowDefinition()['starting_stage'] ?? '';

        try {
            FlowDefinition::create(
                self::stageNames($entityInstance->flowDefinition()),
                is_string($starting) ? $starting : '',
                [],
            );
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());

            return;
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceGameSystem);

        try {
            $this->flowHandler->handle(self::updateFlowCommand($entityInstance));

            if ($entityInstance->sheetStructure() !== null) {
                $this->sheetHandler->handle(self::updateSheetCommand($entityInstance));
            }

            parent::updateEntity($entityManager, $entityInstance);
        } catch (\DomainException $e) {
            // FR-005: occupied stages must not be orphaned.
            $this->addFlash('danger', $e->getMessage());
            $entityManager->refresh($entityInstance);
        } catch (OptimisticLockException) {
            // Edge case §8: concurrent supersede.
            $this->addFlash('warning', self::SUPERSEDED_MESSAGE);
            $entityManager->refresh($entityInstance);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function stageNames(array $payload): array
    {
        $names = [];
        foreach (is_array($payload['stages'] ?? null) ? $payload['stages'] : [] as $stage) {
            if (is_string($stage)) {
                $names[] = $stage;
            } elseif (is_array($stage) && is_string($stage['name'] ?? null)) {
                $names[] = $stage['name'];
            }
        }

        return $names;
    }

    private static function updateFlowCommand(PersistenceGameSystem $row): UpdateFlowDefinitionCommand
    {
        $stageNames = self::stageNames($row->flowDefinition());

        /** @var list<array{from: string, to: string}> $transitions */
        $transitions = [];
        foreach (is_array($row->flowDefinition()['transitions'] ?? null) ? $row->flowDefinition()['transitions'] : [] as $transition) {
            if (is_array($transition)) {
                $transitions[] = [
                    'from' => (string) ($transition['from'] ?? ''),
                    'to' => (string) ($transition['to'] ?? ''),
                ];
            }
        }

        $starting = $row->flowDefinition()['starting_stage'] ?? '';

        return new UpdateFlowDefinitionCommand(
            \App\Shared\Domain\Identifier\GameSystemId::fromString($row->id()),
            $stageNames,
            is_string($starting) ? $starting : '',
            $transitions,
        );
    }

    private static function updateSheetCommand(PersistenceGameSystem $row): UpdateSheetStructureCommand
    {
        $payload = $row->sheetStructure() ?? ['fields' => [], 'version' => 1];

        /** @var list<FieldDefinition> $fields */
        $fields = [];
        foreach (is_array($payload['fields'] ?? null) ? $payload['fields'] : [] as $field) {
            if (is_array($field)) {
                $fields[] = FieldDefinition::fromArray($field);
            }
        }

        return new UpdateSheetStructureCommand(
            \App\Shared\Domain\Identifier\GameSystemId::fromString($row->id()),
            $fields,
        );
    }
}
