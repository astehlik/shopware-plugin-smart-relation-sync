<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\ApiDefinition;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\JsonResponse;

final class EntitySchemaGeneratorDecoratorTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testApiSchemaReturnsExpectedProperties(): void
    {
        $this->getBrowser()->jsonRequest(
            'GET',
            '/api/_info/entity-schema.json',
        );

        $response = $this->getBrowser()->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);

        $json = $response->getContent();

        self::assertIsString($json);
        self::assertNotEmpty($json);

        $json = json_decode($json, true);

        self::assertIsArray($json);

        self::assertIsArray($json['product']);
        self::assertIsArray($json['product']['properties']);
        self::assertIsArray($json['product']['properties']['categoriesCleanupRelations']);
        self::assertSame('boolean', $json['product']['properties']['categoriesCleanupRelations']['type']);

        self::assertIsArray($json['property_group_option']);
        self::assertIsArray($json['property_group_option']['properties']);
        self::assertIsArray($json['property_group_option']['properties']['excludedOptions']);
        self::assertIsArray($json['property_group_option']['properties']['excludedOptionsCleanupRelations']);
        self::assertSame('boolean', $json['property_group_option']['properties']['excludedOptionsCleanupRelations']['type']);
    }
}
