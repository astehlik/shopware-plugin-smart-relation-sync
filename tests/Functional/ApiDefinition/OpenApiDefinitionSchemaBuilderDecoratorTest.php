<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\ApiDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Swh\SmartRelationSync\ApiDefinition\OpenApiDefinitionSchemaBuilderDecorator;
use Symfony\Component\HttpFoundation\JsonResponse;

#[CoversClass(OpenApiDefinitionSchemaBuilderDecorator::class)]
class OpenApiDefinitionSchemaBuilderDecoratorTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    #[DataProvider('provideApiSchemaReturnsExpectedPropertiesCases')]
    public function testApiSchemaReturnsExpectedProperties(string $type): void
    {
        $this->getBrowser()->jsonRequest(
            'GET',
            '/api/_info/openapi3.json?type=' . $type,
        );

        $response = $this->getBrowser()->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $json = $response->getContent();

        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        $json = json_decode($json, true);

        $this->assertIsArray($json);
        $this->assertIsArray($json['components']);
        $this->assertIsArray($json['components']['schemas']);

        $schemas = $json['components']['schemas'];

        $this->assertIsArray($schemas['Product']);
        $this->assertIsArray($schemas['Product']['properties']);
        $this->assertIsArray($schemas['Product']['properties']['categoriesCleanupRelations']);
        $this->assertSame('boolean', $schemas['Product']['properties']['categoriesCleanupRelations']['type']);

        $this->assertIsArray($schemas['PropertyGroupOption']);
        $this->assertIsArray($schemas['PropertyGroupOption']['properties']);
        $this->assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']);

        $this->assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']);
        $this->assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']['properties']);

        $extensions = $schemas['PropertyGroupOption']['properties']['extensions']['properties'];
        $this->assertIsArray($extensions['excludedOptions']);
        $this->assertIsArray($extensions['excludedOptionsCleanupRelations']);
        $this->assertSame('boolean', $extensions['excludedOptionsCleanupRelations']['type']);
    }

    /**
     * @return non-empty-array<non-empty-string>[]
     */
    public static function provideApiSchemaReturnsExpectedPropertiesCases(): iterable
    {
        return [
            [DefinitionService::TYPE_JSON],
            [DefinitionService::TYPE_JSON_API],
        ];
    }
}
