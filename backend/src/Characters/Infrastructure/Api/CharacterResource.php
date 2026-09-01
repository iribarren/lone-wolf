<?php

declare(strict_types=1);

namespace App\Characters\Infrastructure\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Characters\Infrastructure\Api\Input\SaveCharacterInput;
use App\Characters\Infrastructure\Api\Processor\CreateCharacterProcessor;
use App\Characters\Infrastructure\Api\Processor\UpdateCharacterProcessor;
use App\Characters\Infrastructure\Api\Provider\CharactersProvider;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * US5 player-facing character surface (contract paths /campaigns/{id}/characters,
 * /characters/{characterId}). Sheets render from the system's structure
 * metadata carried alongside each view; breaches answer 422 as a
 * SheetValidationProblem with field-level violations (FR-023).
 */
#[ApiResource(
    shortName: 'Character',
    formats: ['json' => 'application/json'],
    // Keeps an empty `attributes` map an object: the serializer flattens the
    // \ArrayObject to its contents, and without this an empty one would come
    // back as `[]` (see the property docblock below).
    normalizationContext: [AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true],
    operations: [
        new Get(
            uriTemplate: '/campaigns/{campaignId}/characters',
            uriVariables: ['campaignId'],
            provider: Provider\CharactersProvider::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
        ),
        new Post(
            uriTemplate: '/campaigns/{campaignId}/characters',
            uriVariables: ['campaignId'],
            input: Input\SaveCharacterInput::class,
            processor: Processor\CreateCharacterProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY') and is_granted('CAMPAIGN_OWNER', request.get('campaignId'))",
            validationContext: ['skip_validation_groups' => true],
            status: 201,
        ),
        new Patch(
            uriTemplate: '/characters/{characterId}',
            uriVariables: ['characterId'],
            // The character is loaded by the processor through its own
            // ownership-checked handler; without this API Platform reads the
            // item first, finds no item provider and answers 404 before the
            // processor ever runs.
            read: false,
            input: Input\SaveCharacterInput::class,
            processor: Processor\UpdateCharacterProcessor::class,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            validationContext: ['skip_validation_groups' => true],
            status: 200,
        ),
    ],
)]
final readonly class CharacterResource
{
    /**
     * `attributes` is an \ArrayObject rather than an array so that a character
     * whose sheet asks nothing of it still serialises `{}`: PHP encodes an
     * empty array as `[]`, which contradicts the contract's
     * `CharacterWrite.attributes` (type object). CharactersProvider::fromData()
     * is the single place that wraps it.
     *
     * @param \ArrayObject<string, mixed> $attributes
     * @param list<string>         $driftIssues
     * @param list<SheetFieldEntryResource>|null $structureFields
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id = '',
        public string $kind = 'pc',
        public string $name = '',
        #[ApiProperty(schema: ['type' => 'object', 'additionalProperties' => true])]
        public \ArrayObject $attributes = new \ArrayObject(),
        public int $validatedStructureVersion = 0,
        public string $reviewStatus = 'clean',
        public array $driftIssues = [],
        public ?int $structureVersion = null,
        public ?array $structureFields = null,
    ) {
    }
}

/**
 * One field of the sheet structure handed back for dynamic rendering
 * (contract FieldDefinition).
 */
final readonly class SheetFieldEntryResource
{
    /**
     * @param list<string> $options
     */
    public function __construct(
        public string $key = '',
        public string $label = '',
        public string $type = 'text',
        public bool $requiredForPc = false,
        public bool $requiredForNpc = false,
        public array $options = [],
    ) {
    }
}
