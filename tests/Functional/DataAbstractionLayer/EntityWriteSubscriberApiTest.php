<?php

declare(strict_types=1);

namespace Functional\DataAbstractionLayer;

use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer\AbstractEntityWriteSubscriberTestCase;

class EntityWriteSubscriberApiTest extends AbstractEntityWriteSubscriberTestCase
{
    use AdminApiTestBehaviour;

    protected function upsertProduct(array $payload): void
    {
        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/sync',
            [
                'write-product' => [
                    'entity' => 'product',
                    'action' => 'upsert',
                    'payload' => [$payload],
                ],
            ],
        );

        $response = $this->getBrowser()->getResponse();

        self::assertSame(200, $response->getStatusCode(), $response->getContent() ?: '');
    }
}
