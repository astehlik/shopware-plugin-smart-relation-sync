<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\ApiDefinition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\JsonResponse;

class OpenApiDefinitionSchemaBuilderDecoratorTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @return non-empty-array<non-empty-string>[]
     */
    public static function provideOpenApiTypes(): array
    {
        return [
            [DefinitionService::TYPE_JSON],
            [DefinitionService::TYPE_JSON_API],
        ];
    }

    #[DataProvider('provideOpenApiTypes')]
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
        self::assertIsArray($json['components']['schemas']['Product']);
        self::assertIsArray($json['components']['schemas']['Product']['properties']);
        self::assertIsArray($json['components']['schemas']['Product']['properties']['categoriesCleanupRelations']);
        self::assertSame('boolean', $json['components']['schemas']['Product']['properties']['categoriesCleanupRelations']['type']);
    }
}
