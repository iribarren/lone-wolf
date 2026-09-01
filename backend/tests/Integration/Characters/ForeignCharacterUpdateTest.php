<?php

declare(strict_types=1);

namespace App\Tests\Integration\Characters;

use App\Campaigns\Application\Command\StartCampaignCommand;
use App\Campaigns\Application\StartCampaignHandler;
use App\Characters\Application\Command\CreateCharacterCommand;
use App\Characters\Application\CreateCharacterHandler;
use App\Characters\Application\Port\CharacterRepositoryInterface;
use App\Identity\Application\UserRepositoryInterface;
use App\Identity\Domain\User;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Rulesets\Application\Command\CreateGameSystemCommand;
use App\Rulesets\Application\Command\UpdateSheetStructureCommand;
use App\Rulesets\Application\CreateGameSystemHandler;
use App\Rulesets\Application\UpdateSheetStructureHandler;
use App\Rulesets\Domain\FieldDefinition;
use App\Shared\Domain\Identifier\CampaignId;
use App\Shared\Domain\Identifier\CharacterId;
use App\Shared\Domain\Identifier\UserId;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * C12: `PATCH /api/characters/{characterId}` is the only campaign-scoped
 * write with no operation-level CAMPAIGN_OWNER expression — the route carries
 * no campaign id, so ownership is enforced one layer down, by
 * UpdateCharacterHandler resolving the character's campaign through
 * OwnedCampaignFetcher. Every other ownership path has a test; this one had
 * none, which is exactly how that call gets refactored away unnoticed.
 *
 * FR-019: a foreign id reads as unknown, so the refusal is a 404, never a 403
 * that would confirm the character exists.
 */
final class ForeignCharacterUpdateTest extends WebTestCase
{
    public function testAForeignPlayerCannotPatchSomeoneElsesCharacter(): void
    {
        $client = static::createClient();

        $owner = $this->registerPlayer('owner');
        $characterId = $this->createCharacterFor($owner);
        $intruder = $this->registerPlayer('intruder');

        $this->patchCharacter($client, $intruder, $characterId, [
            'kind' => 'pc',
            'name' => 'Stolen',
            'attributes' => ['hp' => 3],
        ]);

        self::assertSame(
            404,
            $client->getResponse()->getStatusCode(),
            'A foreign player must not be able to update another player\'s character.',
        );

        // The status alone is not the guarantee: the refusal must happen
        // BEFORE the write. Without the handler's ownership check the update
        // persists and only the read-side projection then answers 404 — a
        // silent foreign write behind a correct-looking status code.
        $characters = static::getContainer()->get(CharacterRepositoryInterface::class);
        \assert($characters instanceof CharacterRepositoryInterface);

        $stored = $characters->get(CharacterId::fromString($characterId));
        self::assertNotNull($stored);
        self::assertSame('Vex', $stored->name(), 'The foreign PATCH must not have been applied.');
        self::assertSame(['hp' => 5], $stored->attributes()->toArray());
    }

    /**
     * The same request from the owner succeeds — proving the 404 above is the
     * ownership check and not a broken route, payload or fixture.
     */
    public function testTheOwningPlayerCanPatchTheSameCharacter(): void
    {
        $client = static::createClient();

        $owner = $this->registerPlayer('owner');
        $characterId = $this->createCharacterFor($owner);

        $this->patchCharacter($client, $owner, $characterId, [
            'kind' => 'pc',
            'name' => 'Renamed',
            'attributes' => ['hp' => 3],
        ]);

        self::assertSame(200, $client->getResponse()->getStatusCode());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patchCharacter(
        KernelBrowser $client,
        User $player,
        string $characterId,
        array $payload,
    ): void {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);
        \assert($jwt instanceof JWTTokenManagerInterface);

        $client->request(
            'PATCH',
            sprintf('/api/characters/%s', $characterId),
            [],
            [],
            [
                'HTTP_Authorization' => sprintf('Bearer %s', $jwt->create(new SecurityUser($player))),
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    private function createCharacterFor(User $player): string
    {
        $container = static::getContainer();

        $createSystem = $container->get(CreateGameSystemHandler::class);
        \assert($createSystem instanceof CreateGameSystemHandler);
        $updateStructure = $container->get(UpdateSheetStructureHandler::class);
        \assert($updateStructure instanceof UpdateSheetStructureHandler);
        $startCampaign = $container->get(StartCampaignHandler::class);
        \assert($startCampaign instanceof StartCampaignHandler);
        $createCharacter = $container->get(CreateCharacterHandler::class);
        \assert($createCharacter instanceof CreateCharacterHandler);

        $systemId = $createSystem->handle(new CreateGameSystemCommand(
            name: sprintf('foreign-patch-%s', bin2hex(random_bytes(4))),
            description: 'Ownership fixture.',
            stageNames: ['Scene', 'Sequel'],
            startingStage: 'Scene',
            transitions: [],
        ));

        $updateStructure->handle(new UpdateSheetStructureCommand(
            $systemId,
            [FieldDefinition::number('hp', 'Hit points', requiredForPc: true, requiredForNpc: false)],
        ));

        $started = $startCampaign->handle(new StartCampaignCommand($player->id(), $systemId));

        $character = $createCharacter->handle(new CreateCharacterCommand(
            $player->id(),
            CampaignId::fromString($started->campaignId),
            kind: 'pc',
            name: 'Vex',
            attributes: ['hp' => 5],
        ));

        return $character->id()->toString();
    }

    private function registerPlayer(string $prefix): User
    {
        $users = static::getContainer()->get(UserRepositoryInterface::class);
        \assert($users instanceof UserRepositoryInterface);

        $user = User::register(
            UserId::generate(),
            sprintf('%s-%s@example.com', $prefix, bin2hex(random_bytes(4))),
            'integration-test-hash',
        );
        $users->save($user);

        return $user;
    }
}
