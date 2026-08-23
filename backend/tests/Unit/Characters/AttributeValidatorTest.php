<?php

declare(strict_types=1);

namespace App\Tests\Unit\Characters;

use App\Characters\Domain\AttributeValidator;
use App\Characters\Domain\CharacterKind;
use App\Characters\Domain\SheetField;
use App\Characters\Domain\SheetSchema;
use PHPUnit\Framework\TestCase;

/**
 * FR-022..FR-024 write-time validation: PCs satisfy every requiredForPc
 * field, NPCs only requiredForNpc ones (FR-024); type and select-option
 * breaches produce per-field messages keyed by attribute key (FR-023);
 * unknown keys are refused outright.
 */
final class AttributeValidatorTest extends TestCase
{
    private AttributeValidator $validator;

    private SheetSchema $schema;

    protected function setUp(): void
    {
        $this->validator = new AttributeValidator();
        $this->schema = new SheetSchema(3, [
            SheetField::number('hp', 'Hit points', requiredForPc: true, requiredForNpc: false),
            SheetField::select('class', 'Class', ['Fighter', 'Mage'], requiredForPc: true, requiredForNpc: false),
            SheetField::text('bond', 'Bond', requiredForPc: false, requiredForNpc: true),
        ]);
    }

    public function testConformingPcPassesWithNoViolations(): void
    {
        $violations = $this->validator->validate([
            'hp' => 12,
            'class' => 'Mage',
        ], CharacterKind::Pc, $this->schema);

        self::assertSame([], $violations);
    }

    public function testMissingPcRequiredFieldIsReportedAgainstItsKey(): void
    {
        $violations = $this->validator->validate([
            'hp' => 10,
            // 'class' missing — required for a PC.
        ], CharacterKind::Pc, $this->schema);

        self::assertCount(1, $violations);
        self::assertSame('class', $violations[0]->field);
        self::assertNotSame('', $violations[0]->message);
    }

    public function testNpcSkipsRequiredForPcFieldsButKeepsItsOwn(): void
    {
        // FR-024: the lighter NPC set — no hp/class needed, bond is.
        self::assertSame([], $this->validator->validate(['bond' => 'Sworn to the wolf.'], CharacterKind::Npc, $this->schema));

        $missingBond = $this->validator->validate([], CharacterKind::Npc, $this->schema);
        self::assertCount(1, $missingBond);
        self::assertSame('bond', $missingBond[0]->field);
    }

    public function testNumberFieldRejectsTextAndBooleans(): void
    {
        $asText = $this->validator->validate(['hp' => 'twelve', 'class' => 'Fighter'], CharacterKind::Pc, $this->schema);
        self::assertCount(1, $asText);
        self::assertSame('hp', $asText[0]->field);

        $asBool = $this->validator->validate(['hp' => true, 'class' => 'Fighter'], CharacterKind::Pc, $this->schema);
        self::assertCount(1, $asBool);
        self::assertSame('hp', $asBool[0]->field);
    }

    public function testSelectFieldRefusesValuesOutsideItsOptions(): void
    {
        $violations = $this->validator->validate(['hp' => 8, 'class' => 'Bard'], CharacterKind::Pc, $this->schema);

        self::assertCount(1, $violations);
        self::assertSame('class', $violations[0]->field);
    }

    public function testTextFieldRejectsNumbers(): void
    {
        $violations = $this->validator->validate(
            ['bond' => 7],
            CharacterKind::Npc,
            $this->schema,
        );

        self::assertCount(1, $violations);
        self::assertSame('bond', $violations[0]->field);
    }

    public function testUnknownKeysAreRejected(): void
    {
        $violations = $this->validator->validate([
            'hp' => 9,
            'class' => 'Fighter',
            'spellSlots' => 4, // not part of this sheet at all.
        ], CharacterKind::Pc, $this->schema);

        self::assertCount(1, $violations);
        self::assertSame('spellSlots', $violations[0]->field);
    }

    public function testEveryViolationCarriesFieldAndMessage(): void
    {
        $violations = $this->validator->validate([
            'class' => 'Bard',
            'bond' => 42,
            'spellSlots' => 4,
        ], CharacterKind::Pc, $this->schema);

        self::assertGreaterThanOrEqual(3, \count($violations));

        foreach ($violations as $violation) {
            self::assertNotSame('', $violation->field);
            self::assertNotSame('', $violation->message);
        }
    }
}
