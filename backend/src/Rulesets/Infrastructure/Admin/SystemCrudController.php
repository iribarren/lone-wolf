<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Application\FlowFactory;
use App\Rulesets\Application\Command\SetSystemStatusCommand;
use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\SetSystemStatusHandler;
use App\Rulesets\Application\UpdateSheetStructureHandler;
use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\GameSystemStatus;
use App\Rulesets\Infrastructure\Admin\Form\FlowDefinitionType;
use App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem;
use App\Shared\Domain\Identifier\GameSystemId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<PersistenceGameSystem>
 */
final class SystemCrudController extends AbstractCrudController
{
    use UpdatesFlowDefinition;

    public function __construct(
        private readonly UpdateSheetStructureHandler $sheetHandler,
        private readonly SetSystemStatusHandler $statusHandler,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PersistenceGameSystem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        // Without these EasyAdmin titles the pages from the persistence class
        // name — "Create PersistenceGameSystem" (audit C3).
        return $crud
            ->setEntityLabelInSingular('Game system')
            ->setEntityLabelInPlural('Game systems');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('name')->add('status');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createEntity(string $entityFqcn): PersistenceGameSystem
    {
        // EasyAdmin cannot instantiate the persistence row otherwise: every
        // constructor argument is mandatory. Admins get a minimal editable
        // Scene/Sequel seed and must confirm a valid flow on save (FR-002..004).
        return new $entityFqcn(
            GameSystemId::generate()->toString(),
            '',
            '',
            GameSystemStatus::Active,
            [
                'stages' => [
                    ['name' => 'Scene', 'guidance' => 'Open your scene and move the story forward.'],
                    ['name' => 'Sequel', 'guidance' => 'React to what just happened.'],
                ],
                'starting_stage' => 'Scene',
                'transitions' => [],
            ],
            null,
        );
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

        // jsonb payloads are not stringable: EasyAdmin's TextConfigurator throws
        // on list/detail pages before any formatting could run. ArrayField is
        // the array-tolerant concrete type; the initial flow is authored here,
        // later edits happen in the dedicated Campaign flows section.
        yield ArrayField::new('flowDefinition', 'Campaign flow')
            ->setFormType(FlowDefinitionType::class)
            ->onlyWhenCreating()
            ->setHelp('At least two stages and one starting stage. Transitions can be wired after creation under "Campaign flows".');

        yield ArrayField::new('sheetStructure', 'Character sheet structure (JSON)')
            ->setFormType(JsonDocumentType::class)
            ->setFormTypeOption(JsonDocumentType::OPTION_IS_SHEET, true)
            ->onlyOnForms();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceGameSystem);

        if (trim($entityInstance->name()) === '') {
            $this->addFlash('danger', 'Game system names must be non-empty.');

            return;
        }

        $payload = $entityInstance->flowDefinition();

        try {
            // Full structural validation — stages, starting stage AND
            // transitions — before the first save touches storage.
            FlowFactory::fromPayload(
                self::stageNames($payload),
                self::startingStage($payload),
                self::transitions($payload),
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
            if ($entityInstance->sheetStructure() !== null) {
                $this->sheetHandler->handle(self::updateSheetCommand($entityInstance));
            }

            // FR-001/FR-006: availability is a use case, not a column write.
            // The form mapper has already put the submitted status on the row —
            // the same shape GameFlowCrudController relies on — so the command
            // carries it into the Application layer, which is what decides
            // whether the aggregate activates or deactivates (Constitution I).
            $this->statusHandler->handle(new SetSystemStatusCommand(
                GameSystemId::fromString($entityInstance->id()),
                $entityInstance->status() === GameSystemStatus::Active,
            ));

            parent::updateEntity($entityManager, $entityInstance);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
            $entityManager->refresh($entityInstance);
        } catch (OptimisticLockException) {
            // Edge case §8: concurrent supersede. The failed flush already
            // closed the EntityManager, so the row cannot be refreshed here —
            // the warning tells the author to reload and re-apply, and the
            // next request reads the winning version.
            $this->addFlash('warning', self::SUPERSEDED_MESSAGE);
        }
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
            GameSystemId::fromString($row->id()),
            $fields,
        );
    }
}
