<?php

declare(strict_types=1);

namespace App\Tests\Unit\Oracles\Infrastructure\Form;

use App\Oracles\Domain\OracleEntry;
use App\Oracles\Infrastructure\Admin\Form\OracleEntriesCollectionType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Kernel-less proof (Constitution IV) that the entries editor binds the jsonb
 * entries payload directly, drops blank rows, and deliberately lets an invalid
 * weight through — that refusal is the OracleEntry aggregate's to make, not a
 * generic form error's.
 */
final class OracleEntriesCollectionTypeTest extends \PHPUnit\Framework\TestCase
{
    private FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = (new FormFactoryBuilder())
            ->addExtension(new CoreExtension())
            ->getFormFactory();
    }

    public function testStoredPayloadPopulatesTheEditor(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, [
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000001', 'text' => 'Clear skies.', 'weight' => 3],
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000002', 'text' => 'Storm rolling in.', 'weight' => 1],
        ]);

        self::assertCount(2, $form);
        self::assertSame('Clear skies.', $form->get('0')->get('text')->getData());
        self::assertSame(3, $form->get('0')->get('weight')->getData());
        self::assertSame('Storm rolling in.', $form->get('1')->get('text')->getData());

        // The editor renders only what an author writes: the stored id is
        // not a field, so it is absent from the row the editor works with.
        self::assertSame(['text' => 'Clear skies.', 'weight' => 3], $form->get('0')->getNormData());
    }

    public function testSubmissionNormalizesBackToTheStorageShape(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, [
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000001', 'text' => 'Clear skies.', 'weight' => 3],
        ]);

        $form->submit([
            ['text' => 'Clear skies.', 'weight' => '3'],
            ['text' => 'Storm rolling in.', 'weight' => '1'],
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertSame([
            ['text' => 'Clear skies.', 'weight' => 3],
            ['text' => 'Storm rolling in.', 'weight' => 1],
        ], $form->getData());
    }

    public function testBlankRowsAreDroppedOnSubmit(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, []);

        $form->submit([
            ['text' => 'Ambush.', 'weight' => '2'],
            ['text' => '', 'weight' => ''],
            ['text' => '   ', 'weight' => ''],
            ['text' => 'Quiet trail.', 'weight' => '1'],
        ]);

        // Dropped rows leave no gap: storage must stay a plain list, or the
        // jsonb column round-trips as an object with numeric keys.
        self::assertSame([
            ['text' => 'Ambush.', 'weight' => 2],
            ['text' => 'Quiet trail.', 'weight' => 1],
        ], $form->getData());
    }

    public function testDeletingARowRemovesExactlyThatEntry(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, [
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000001', 'text' => 'Ambush.', 'weight' => 2],
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000002', 'text' => 'Quiet trail.', 'weight' => 1],
            ['id' => 'a3f0c2d4-0000-4000-8000-000000000003', 'text' => 'Lost.', 'weight' => 4],
        ]);

        // The browser posts the surviving rows keeping their original indices.
        $form->submit([
            0 => ['text' => 'Ambush.', 'weight' => '2'],
            2 => ['text' => 'Lost.', 'weight' => '4'],
        ]);

        self::assertSame([
            ['text' => 'Ambush.', 'weight' => 2],
            ['text' => 'Lost.', 'weight' => 4],
        ], $form->getData());
    }

    public function testAWeightLeftBlankBesideAResultMeansOne(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, []);

        $form->submit([['text' => 'Ambush.', 'weight' => '']]);

        self::assertSame([['text' => 'Ambush.', 'weight' => 1]], $form->getData());
    }

    public function testMalformedPayloadFallsBackToAnEmptyStructure(): void
    {
        foreach ([null, [], 'nope', ['nope'], [null, 42]] as $payload) {
            $form = $this->factory->create(OracleEntriesCollectionType::class, $payload);

            self::assertCount(0, $form, sprintf('Payload %s should yield no rows.', var_export($payload, true)));
            self::assertSame([], $form->getData(), sprintf('Payload %s should normalize to an empty list.', var_export($payload, true)));
        }
    }

    /**
     * The editor accepts a non-positive weight on purpose so the author reads
     * the aggregate's own words rather than a generic constraint message.
     */
    #[DataProvider('refusedWeights')]
    public function testTheEditorAcceptsWhatTheDomainRefuses(int $weight): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, []);
        $form->submit([['text' => 'Ambush.', 'weight' => (string) $weight]]);

        self::assertTrue($form->isSynchronized());
        self::assertSame([['text' => 'Ambush.', 'weight' => $weight]], $form->getData());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Oracle entry weights must be positive integers');

        OracleEntry::place('Ambush.', $weight);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedWeights(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-3];
    }

    public function testTheDomainRefusesBlankTextTheEditorCouldStillSubmit(): void
    {
        $form = $this->factory->create(OracleEntriesCollectionType::class, []);

        // Text is blank but a weight was typed, so the row is not "blank" and
        // survives the drop — the aggregate is what turns it away.
        $form->submit([['text' => '', 'weight' => '2']]);

        self::assertSame([['text' => '', 'weight' => 2]], $form->getData());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Oracle entry text must be non-empty.');

        OracleEntry::place('', 2);
    }
}
