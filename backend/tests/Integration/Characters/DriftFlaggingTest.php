<?php

declare(strict_types=1);

namespace App\Tests\Integration\Characters;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Characters\Application\Command\CreateCharacterCommand;
use App\Characters\Application\CreateCharacterHandler;
use App\Characters\Application\ListCharactersHandler;
use App\Characters\Application\Query\ListCharactersQuery;
use App\Characters\Domain\ReviewStatus;
use App\Campaigns\Domain\CampaignAccessDeniedException;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\UpdateSheetStructureHandler;
use App\Rulesets\Domain\FieldDefinition;
use App\Shared\Domain\Identifier\UserId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * US5 persistence contract against real PostgreSQL (FR-025):
 *
 * - JSONB attributes survive a full round trip untouched;
 * - when the owning system's sheet structure is later bumped so that stored
 *   data no longer conforms, the character surfaces as
 *   flagged_for_review with drift issues — still readable, never mutated;
 * - reads stay owner-scoped (FR-019).
 */
final class DriftFlaggingTest extends KernelTestCase
{
    private CreateCharacterHandler $createCharacter;

    private ListCharactersHandler $listCharacters;

    private CharacterRepositoryInterface $characters;

    private UpdateSheetStructureHandler $updateStructure;

    private \App\Rulesets\Application\CreateGameSystemHandler $createSystem;

    private \App\Campaigns\Application\StartCampaignHandler $startCampaign;

    private UserRepositoryInterface $users;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $pull = static function (string $id) use ($container): object {
            $service = $container->get($id);
            \assert(\is_object($service));

            return $service;
        };

        /** @var CreateCharacterHandler $createCharacter */
        $createCharacter = $pull(CreateCharacterHandler::class);
        /** @var ListCharactersHandler $listCharacters */
        $listCharacters = $pull(ListCharactersHandler::class);
        /** @var CharacterRepositoryInterface $characters */
        $characters = $pull(CharacterRepositoryInterface::class);
        /** @var UpdateSheetStructureHandler $updateStructure */
        $updateStructure = $pull(UpdateSheetStructureHandler::class);
        /** @var \App\Rulesets\Application\CreateGameSystemHandler $createSystem */
        $createSystem = $pull(\App\Rulesets\Application\CreateGameSystemHandler::class);
        /** @var \App\Campaigns\Application\StartCampaignHandler $startCampaign */
        $startCampaign = $pull(\App\Campaigns\Application\StartCampaignHandler::class);
        /** @var UserRepositoryInterface $users */
        $users = $pull(UserRepositoryInterface::class);

        $this->createCharacter = $createCharacter;
        $this->listCharacters = $listCharacters;
        $this->characters = $characters;
        $this->updateStructure = $updateStructure;
        $this->createSystem = $createSystem;
        $this->startCampaign = $startCampaign;
        $this->users = $users;
    }

    public function testAttributesRoundTripThroughJsonbUntouched(): void
    {
        [$player, $campaign] = $this->playerCampaignFixture();

        $created = $this->createCharacter->handle(new CreateCharacterCommand(
            $player,
            $campaign,
            kind: 'pc',
            name: 'Vex',
            attributes: ['hp' => 14],
        ));

        // Fresh read through persistence — nothing held in memory.
        $entityManager = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        \assert($entityManager instanceof \Doctrine\ORM\EntityManagerInterface);
        $entityManager->clear();

        $stored = $this->characters->get($created->id());
        self::assertNotNull($stored);
        self::assertSame('Vex', $stored->name());
        self::assertSame(['hp' => 14], $stored->attributes()->toArray());
        self::assertSame(ReviewStatus::Clean, $stored->reviewStatus());
    }

    public function testStructuralBumpFlagsStoredDataWithoutTouchingIt(): void
    {
        [$player, $campaign, $systemId] = $this->playerCampaignFixture();

        $created = $this->createCharacter->handle(new CreateCharacterCommand(
            $player,
            $campaign,
            kind: 'pc',
            name: 'Orrin',
            attributes: ['hp' => 10],
        ));

        // The admin reshapes the sheet: hit points are gone entirely.
        $this->updateStructure->handle(new UpdateSheetStructureCommand(
            $systemId,
            [FieldDefinition::select('class', 'Class', ['Fighter', 'Mage'], requiredForPc: true)],
        ));

        $view = $this->listCharacters->handle(new ListCharactersQuery($player, $campaign))[0];

        self::assertSame(ReviewStatus::FlaggedForReview->value, $view->reviewStatus);
        self::assertNotSame([], $view->driftIssues);

        // FR-025: readable and untouched — never silently altered or dropped.
        self::assertSame(['hp' => 10], $view->attributes);
        self::assertSame(1, $view->validatedStructureVersion);

        $stillStored = $this->characters->get($created->id());
        self::assertNotNull($stillStored);
        self::assertSame(ReviewStatus::Clean, $stillStored->reviewStatus(), 'Flagging is a read-time projection until someone re-saves.');
        self::assertSame(1, $stillStored->validatedStructureVersion());
    }

    public function testForeignPlayersCannotListSomeoneElsesCharacters(): void
    {
        [$player, $campaign] = $this->playerCampaignFixture();
        $intruder = $this->registerPlayer('intruder');

        $this->expectException(CampaignAccessDeniedException::class);
        $this->listCharacters->handle(new ListCharactersQuery($intruder, $campaign));
    }

    /**
     * @return array{UserId, \App\Shared\Domain\Identifier\CampaignId, \App\Shared\Domain\Identifier\GameSystemId}
     */
    private function playerCampaignFixture(): array
    {
        $player = $this->registerPlayer();
        $systemId = $this->createSystem->handle(
            new \App\Rulesets\Application\Command\CreateGameSystemCommand(
                name: sprintf('sheets-%s', bin2hex(random_bytes(4))),
                description: 'Character sheet fixture.',
                stageNames: ['Scene', 'Sequel'],
                startingStage: 'Scene',
                transitions: [],
            ),
        );

        $this->updateStructure->handle(new UpdateSheetStructureCommand(
            $systemId,
            [
                FieldDefinition::number('hp', 'Hit points', requiredForPc: true, requiredForNpc: false),
                FieldDefinition::text('bond', 'Bond', requiredForPc: false, requiredForNpc: true),
            ],
        ));

        $started = $this->startCampaign->handle(new StartCampaignCommand($player, $systemId));

        return [$player, \App\Shared\Domain\Identifier\CampaignId::fromString($started->campaignId), $systemId];
    }

    private function registerPlayer(string $prefix = 'sheets'): UserId
    {
        $user = User::register(
            UserId::generate(),
            sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(4))),
            'integration-test-hash',
        );
        $this->users->save($user);

        return $user->id();
    }
}
