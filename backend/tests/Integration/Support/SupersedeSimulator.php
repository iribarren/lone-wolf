<?php

declare(strict_types=1);

namespace App\Tests\Integration\Support;

use App\Rulesets\Infrastructure\Persistence\PersistenceGameSystem;
use Doctrine\DBAL\Connection;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test-env only: plays the second admin in a concurrent-supersede race
 * (edge case §8). Armed by a test, it bumps the @Version column of the row
 * being edited after EasyAdmin has loaded it and before the CRUD controller
 * flushes — exactly the window in which another admin's save lands first.
 */
final class SupersedeSimulator
{
    /** Disarmed by default so it never disturbs the other backoffice tests. */
    public static bool $armed = false;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param BeforeEntityUpdatedEvent<object> $event
     */
    #[AsEventListener(event: BeforeEntityUpdatedEvent::class)]
    public function supersede(BeforeEntityUpdatedEvent $event): void
    {
        if (!self::$armed) {
            return;
        }

        self::$armed = false;
        $entity = $event->getEntityInstance();

        if (!$entity instanceof PersistenceGameSystem) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE game_systems SET version = version + 1 WHERE id = ?',
            [$entity->id()],
        );
    }
}
