<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

class EntityWriteSubscriberDalTest extends AbstractEntityWriteSubscriberTestCase
{
    protected function upsertProduct(array $payload): void
    {
        $this->getContainer()->get('product.repository')
            ->upsert([$payload], $this->context);
    }
}
