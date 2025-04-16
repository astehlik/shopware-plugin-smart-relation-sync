<?php

declare(strict_types=1);

namespace Functional\DataAbstractionLayer;

use Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer\AbstractEntityWriteSubscriberTestCase;

class EntityWriteSubscriberDalTest extends AbstractEntityWriteSubscriberTestCase
{
    protected function upsertProduct(array $payload): void
    {
        $this->getContainer()->get('product.repository')
            ->upsert([$payload], $this->context);
    }
}
