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

        $this->assertInstanceOf(JsonResponse::class, $response);

        $json = $response->getContent();

        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        $json = json_decode($json, true);

        $this->assertIsArray($json);

        $this->assertIsArray($json['product']);
        $this->assertIsArray($json['product']['properties']);
        $this->assertIsArray($json['product']['properties']['categoriesCleanupRelations']);
        $this->assertSame('boolean', $json['product']['properties']['categoriesCleanupRelations']['type']);

        $this->assertIsArray($json['property_group_option']);
        $this->assertIsArray($json['property_group_option']['properties']);
        $this->assertIsArray($json['property_group_option']['properties']['excludedOptions']);
        $this->assertIsArray($json['property_group_option']['properties']['excludedOptionsCleanupRelations']);
        $this->assertSame('boolean', $json['property_group_option']['properties']['excludedOptionsCleanupRelations']['type']);
    }
}
