<?php

declare(strict_types=1);

namespace App\Rulesets\Infrastructure\Admin;

use App\Rulesets\Application\UpdateFlowDefinitionHandler;
use App\Rulesets\Domain\GameSystemStatus;
use App\Rulesets\Infrastructure\Admin\Form\FlowDefinitionType;
use App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Dedicated "Campaign flows" backoffice section (FR-002..FR-005): one flow per
 * game system, so this CRUD lists systems and only edits their flow — creating
 * or deleting rows here is impossible by design.
 *
 * @extends AbstractCrudController<PersistenceGameSystem>
 */
final class GameFlowCrudController extends AbstractCrudController
{
    use UpdatesFlowDefinition;

    public function __construct(
        private readonly UpdateFlowDefinitionHandler $flowHandler,
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
        return $actions->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn(6);

        yield TextField::new('name', 'Game system')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Each system owns exactly one campaign flow — edit stages and transitions here.');

        yield ChoiceField::new('status', 'Availability')
            ->setChoices(array_combine(
                array_map(static fn (GameSystemStatus $s): string => ucfirst($s->value), GameSystemStatus::cases()),
                GameSystemStatus::cases(),
            ))
            ->setFormTypeOption('disabled', true)
            ->hideOnForm();

        yield ArrayField::new('flowDefinition', 'Campaign flow')
            ->setFormType(FlowDefinitionType::class)
            ->hideOnIndex();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        \assert($entityInstance instanceof PersistenceGameSystem);

        try {
            // FR-005: occupancy-aware validation + save via the application handler.
            $this->flowHandler->handle(self::updateFlowCommand($entityInstance));

            parent::updateEntity($entityManager, $entityInstance);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
            $entityManager->refresh($entityInstance);
        } catch (OptimisticLockException) {
            // Edge case §8: concurrent supersede.
            $this->addFlash('warning', self::SUPERSEDED_MESSAGE);
            $entityManager->refresh($entityInstance);
        }
    }
}
