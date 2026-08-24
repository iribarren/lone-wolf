<?php

declare(strict_types=1);

namespace App\Tests\Unit\Rulesets\Infrastructure\Form;

use App\Rulesets\Domain\FlowDefinition;
use App\Rulesets\Domain\FlowStage;
use App\Rulesets\Domain\FlowTransition;
use App\Rulesets\Infrastructure\Admin\Form\FlowDefinitionType;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\Forms;

/**
 * Kernel-less proof (Constitution IV) that the structured flow editor binds
 * the jsonb FlowPayload directly and deliberately tolerates unknown stage
 * names — those are refused later by the domain, not by a generic choice error.
 */
final class FlowDefinitionTypeTest extends \PHPUnit\Framework\TestCase
{
    private \Symfony\Component\Form\FormFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = (new FormFactoryBuilder())
            ->addExtension(new CoreExtension())
            ->getFormFactory();
    }

    public function testStoredPayloadPopulatesTheEditor(): void
    {
        $form = $this->factory->create(FlowDefinitionType::class, [
            'stages' => [
                ['name' => 'Scene', 'guidance' => 'Open the scene'],
                ['name' => 'Sequel', 'guidance' => ''],
            ],
            'starting_stage' => 'Scene',
            'transitions' => [
                ['from' => 'Scene', 'to' => 'Sequel'],
            ],
        ]);

        self::assertSame('Scene', $form->get('starting_stage')->getData());
        self::assertCount(2, $form->get('stages'));
        self::assertCount(1, $form->get('transitions'));

        $stored = $form->getData();
        self::assertIsArray($stored);
        self::assertIsArray($stored['transitions']);
        self::assertIsArray($stored['transitions'][0]);
        self::assertSame('Sequel', $stored['transitions'][0]['to']);
    }

    public function testSubmissionNormalizesBackToTheStorageShape(): void
    {
        $form = $this->factory->create(FlowDefinitionType::class, [
            'stages' => [['name' => 'Scene', 'guidance' => '']],
            'starting_stage' => 'Scene',
            'transitions' => [],
        ]);

        $form->submit([
            'stages' => [
                ['name' => 'Scene', 'guidance' => 'Open'],
                ['name' => 'Sequel', 'guidance' => 'React'],
            ],
            'starting_stage' => 'Scene',
            'transitions' => [
                ['from' => 'Scene', 'to' => 'Sequel'],
            ],
        ]);

        self::assertTrue($form->isSynchronized());

        $stored = $form->getData();
        self::assertIsArray($stored);
        self::assertSame([
            'stages' => [
                ['name' => 'Scene', 'guidance' => 'Open'],
                ['name' => 'Sequel', 'guidance' => 'React'],
            ],
            'starting_stage' => 'Scene',
            'transitions' => [
                ['from' => 'Scene', 'to' => 'Sequel'],
            ],
        ], $stored);
    }

    public function testUnknownStageNamesSurviveSubmission(): void
    {
        $form = $this->factory->create(FlowDefinitionType::class, [
            'stages' => [['name' => 'Scene', 'guidance' => '']],
            'starting_stage' => 'Scene',
            'transitions' => [],
        ]);

        $form->submit([
            'stages' => [['name' => 'Scene', 'guidance' => '']],
            'starting_stage' => 'Not-A-Stage',
            'transitions' => [['from' => 'Scene', 'to' => 'Ghost']],
        ]);

        $stored = $form->getData();
        self::assertIsArray($stored);
        self::assertSame('Not-A-Stage', $stored['starting_stage']);
        self::assertIsArray($stored['transitions']);
        self::assertIsArray($stored['transitions'][0]);
        self::assertSame('Ghost', $stored['transitions'][0]['to']);
    }

    public function testDomainRefusesWhatTheEditorAccepts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown stage');

        FlowDefinition::create(
            [
                FlowStage::fromArray(['name' => 'Scene', 'guidance' => '']),
                FlowStage::fromArray(['name' => 'Sequel', 'guidance' => '']),
            ],
            'Scene',
            [FlowTransition::fromNames('Scene', 'Ghost')],
        );
    }

    public function testEmptyStageRowsAreDroppedOnSubmit(): void
    {
        $form = $this->factory->create(FlowDefinitionType::class, [
            'stages' => [['name' => 'Scene', 'guidance' => '']],
            'starting_stage' => 'Scene',
            'transitions' => [],
        ]);

        $form->submit([
            'stages' => [
                ['name' => '', 'guidance' => ''],
                ['name' => 'Sequel', 'guidance' => ''],
            ],
            'starting_stage' => 'Sequel',
            'transitions' => [],
        ]);

        $stored = $form->getData();
        self::assertIsArray($stored);
        $stages = $stored['stages'];
        self::assertIsArray($stages);
        self::assertCount(1, $stages);
        self::assertIsArray($stages[0]);
        self::assertSame('Sequel', $stages[0]['name']);
    }

    public function testMissingOrMalformedPayloadFallsBackToEmptyStructure(): void
    {
        foreach ([null, [], ['stages' => 'nope'], ['starting_stage' => 42]] as $payload) {
            $form = $this->factory->create(FlowDefinitionType::class, $payload);

            self::assertSame([], $form->get('stages')->getData());
            self::assertSame('', $form->get('starting_stage')->getData());
            self::assertSame([], $form->get('transitions')->getData());
        }
    }
}
