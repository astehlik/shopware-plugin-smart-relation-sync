<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;

class EntityWriteSubscriberApiTest extends AbstractEntityWriteSubscriberTestCase
{
    use AdminApiTestBehaviour;

    protected function upsertEntity(string $entity, array $payload): void
    {
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/sync',
            [
                'write-product' => [
                    'entity' => $entity,
                    'action' => 'upsert',
                    'payload' => [$payload],
                ],
            ],
        );

        $response = $this->getBrowser()->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
    }
}
