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

        self::assertInstanceOf(JsonResponse::class, $response);

        $json = $response->getContent();

        self::assertIsString($json);
        self::assertNotEmpty($json);

        $json = json_decode($json, true);

        self::assertIsArray($json);
        self::assertIsArray($json['components']);
        self::assertIsArray($json['components']['schemas']);

        $schemas = $json['components']['schemas'];

        self::assertIsArray($schemas['Product']);
        self::assertIsArray($schemas['Product']['properties']);
        self::assertIsArray($schemas['Product']['properties']['categoriesCleanupRelations']);
        self::assertSame('boolean', $schemas['Product']['properties']['categoriesCleanupRelations']['type']);

        self::assertIsArray($schemas['PropertyGroupOption']);
        self::assertIsArray($schemas['PropertyGroupOption']['properties']);
        self::assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']);

        self::assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']);
        self::assertIsArray($schemas['PropertyGroupOption']['properties']['extensions']['properties']);

        $extensions = $schemas['PropertyGroupOption']['properties']['extensions']['properties'];
        self::assertIsArray($extensions['excludedOptions']);
        self::assertIsArray($extensions['excludedOptionsCleanupRelations']);
        self::assertSame('boolean', $extensions['excludedOptionsCleanupRelations']['type']);
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
