<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class EntityWriteSubscriberDalTest extends AbstractEntityWriteSubscriberTestCase
{
    protected function upsertEntity(string $entity, array $payload): void
    {
        $repository = $this->getContainer()->get($entity . '.repository');

        assert($repository instanceof EntityRepository);

        $repository->upsert([$payload], $this->context);
    }
}
