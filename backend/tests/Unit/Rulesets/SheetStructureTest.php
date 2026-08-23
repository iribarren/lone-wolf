<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rulesets;

use App\Rulesets\Domain\FieldDefinition;
use App\Rulesets\Domain\SheetStructure;
use PHPUnit\Framework\TestCase;

final class SheetStructureTest extends TestCase
{
    public function testAcceptsWellFormedStructureAndStampsVersionOne(): void
    {
        $structure = SheetStructure::create([
            FieldDefinition::select('faction', 'Faction', ['Law', 'Chaos'], requiredForPc: true, requiredForNpc: false),
            FieldDefinition::text('motive', 'Motive', requiredForPc: true, requiredForNpc: true),
        ]);

        self::assertSame(1, $structure->version());
        self::assertSame(['faction', 'motive'], $structure->keys());
    }

    public function testRefusesDuplicateFieldKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unique');

        SheetStructure::create([
            FieldDefinition::text('motive', 'Motive'),
            FieldDefinition::number('motive', 'Motive again'),
        ]);
    }

    public function testSelectFieldRequiresOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('option');

        FieldDefinition::select('faction', 'Faction', []);
    }

    public function testTextFieldRejectsOptions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('select fields only');

        FieldDefinition::text('note', 'Note', options: ['a']);
    }

    public function testRequiredKeysDifferBetweenPcAndNpc(): void
    {
        $structure = SheetStructure::create([
            FieldDefinition::text('name', 'Name', requiredForPc: true, requiredForNpc: true),
            FieldDefinition::number('strain', 'Strain', requiredForPc: true, requiredForNpc: false),
        ]);

        self::assertSame(['name', 'strain'], $structure->requiredKeysForPc());
        self::assertSame(['name'], $structure->requiredKeysForNpc());
    }

    public function testReplacingFieldsBumpsVersionStamp(): void
    {
        $initial = SheetStructure::create([FieldDefinition::text('name', 'Name')]);
        $updated = $initial->withFields([FieldDefinition::text('name', 'Name'), FieldDefinition::number('xp', 'XP')]);

        self::assertSame(1, $initial->version());
        self::assertSame(2, $updated->version());
    }
}
