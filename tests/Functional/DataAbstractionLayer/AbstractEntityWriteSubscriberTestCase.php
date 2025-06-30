<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

abstract class AbstractEntityWriteSubscriberTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    private const string PRODUCT_NUMBER = 'P384584';

    protected Context $context;

    private IdsCollection $ids;

    /**
     * @param non-empty-string $entity
     * @param array<non-empty-string, mixed> $payload
     */
    abstract protected function upsertEntity(string $entity, array $payload): void;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    /**
     * @return array<array{0: bool}>
     */
    public static function trueFalseDataProvider(): array
    {
        return [
            [true],
            [false],
        ];
    }

    public function testSyncManyToMany(): void
    {
        $builder = $this->createProductBuilder()
            ->category('Test 1');

        $this->upsertProductWithRelationCleanup($builder->build());

        $builder = $this->createProductBuilder()
            ->category('Test 2');

        $this->upsertProductWithRelationCleanup($builder->build());

        $categories = $this->loadCategories();
        self::assertCount(1, $categories ?? []);
        self::assertSame($this->ids->get('Test 2'), $categories?->first()?->getId());
    }

    #[DataProvider('trueFalseDataProvider')]
    public function testSyncManyToManyExtension(bool $useExtensionsProperty): void
    {
        $excludedOption1 = ['id' => Uuid::randomHex(), 'name' => 'Excluded option 1', 'group' => ['name' => 'Group 1']];
        $this->upsertEntity('property_group_option', $excludedOption1);

        $excludedOption2 = ['id' => Uuid::randomHex(), 'name' => 'Excluded option 2', 'group' => ['name' => 'Group 2']];
        $this->upsertEntity('property_group_option', $excludedOption2);

        $propertyGroup = $this->buildPropertyGroupData($useExtensionsProperty, $excludedOption1['id']);

        $this->upsertEntity('property_group', $propertyGroup);

        $optionData = $propertyGroup['options'][0];

        match ($useExtensionsProperty) {
            true => $optionData['extensions']['excludedOptions'][0]['id'] = $excludedOption2['id'],
            false => $optionData['excludedOptions'][0]['id'] = $excludedOption2['id'],
        };

        $this->upsertEntity('property_group_option', $optionData);

        $criteria = new Criteria([$optionData['id']]);
        $criteria->addAssociation('excludedOptions');
        $result = $this->getContainer()->get('property_group_option.repository')
            ->search($criteria, $this->context)
            ->first();

        self::assertInstanceOf(PropertyGroupOptionEntity::class, $result);

        $extension = $result->getExtension('excludedOptions');
        self::assertInstanceOf(PropertyGroupOptionCollection::class, $extension);
        self::assertCount(1, $extension);

        self::assertSame($excludedOption2['id'], $extension->first()?->getId());
    }

    public function testSyncManyToManyWithEmptyArray(): void
    {
        $builder = $this->createProductBuilder()
            ->category('Test 1');

        $this->upsertProductWithRelationCleanup($builder->build());

        $payload = $this->createProductBuilder()->build();
        $payload['categories'] = [];

        $this->upsertProductWithRelationCleanup($payload);

        $categories = $this->loadCategories();
        self::assertCount(0, $categories ?? []);
    }

    public function testSyncManyToManyWithoutCleanup(): void
    {
        $builder = $this->createProductBuilder()
            ->category('Test 1');

        $this->upsertEntity('product', $builder->build());

        $builder = $this->createProductBuilder()
            ->category('Test 2');

        $this->upsertEntity('product', $builder->build());

        $categories = $this->loadCategories();
        self::assertCount(2, $categories ?? []);
    }

    public function testSyncManyToManyWithoutPayload(): void
    {
        $builder = $this->createProductBuilder()
            ->category('Test 1');

        $this->upsertProductWithRelationCleanup($builder->build());

        $this->upsertProductWithRelationCleanup($this->createProductBuilder()->build());

        $categories = $this->loadCategories();
        self::assertCount(1, $categories ?? []);
    }

    public function testSyncOneToMany(): void
    {
        $productBuilder = $this->createProductBuilder()
            ->prices('test', 14.28);

        $this->upsertProductWithRelationCleanup($productBuilder->build());

        $productBuilder = $this->createProductBuilder()
            ->prices('test2', 115.0);

        $this->upsertProductWithRelationCleanup($productBuilder->build());

        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('prices');

        $product = $this->searchProductSingle($criteria);
        $prices = $product->getPrices();
        self::assertCount(1, $prices ?? []);
        self::assertSame($this->ids->get('test2'), $prices?->first()?->getRuleId());
    }

    protected function loadCategories(): ?CategoryCollection
    {
        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('categories');

        $product = $this->searchProductSingle($criteria);
        return $product->getCategories();
    }

    /**
     * @return array{
     *     id: non-empty-string,
     *     name: non-empty-string,
     *     options: array{
     *         0: array{
     *             id: non-empty-string,
     *             name: non-empty-string,
     *             extensions?: array{excludedOptions: array{0: array{id: string}}, excludedOptionsCleanupRelations: bool},
     *             excludedOptions?: array{0: array{id: string}},
     *             excludedOptionsCleanupRelations?: bool,
     *         }
     *     }
     * }
     */
    private function buildPropertyGroupData(bool $useExtensionsProperty, string $excludedOptionId): array
    {
        $extensionData = [
            'excludedOptions' => [['id' => $excludedOptionId]],
            'excludedOptionsCleanupRelations' => true,
        ];

        $optionData = [
            'id' => Uuid::randomHex(),
            'name' => 'Test option',
        ];

        match ($useExtensionsProperty) {
            true => $optionData['extensions'] = $extensionData,
            false => $optionData = array_merge($optionData, $extensionData),
        };

        return [
            'id' => Uuid::randomHex(),
            'name' => 'Test group',
            'options' => [$optionData],
        ];
    }

    private function createProductBuilder(): ProductBuilder
    {
        return (new ProductBuilder($this->ids, self::PRODUCT_NUMBER))
            ->name('Test product')
            ->price(11.5);
    }

    /**
     * @return EntityRepository<ProductCollection>
     */
    private function getProductRepository(): EntityRepository
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        assert($productRepository instanceof EntityRepository);
        return $productRepository;
    }

    private function searchProductSingle(Criteria $criteria): ProductEntity
    {
        $product = $this->getProductRepository()->search($criteria, $this->context)->first();

        self::assertInstanceOf(ProductEntity::class, $product);

        return $product;
    }

    /**
     * @param array<mixed> $payload
     */
    private function upsertProductWithRelationCleanup(array $payload): void
    {
        $payload['pricesCleanupRelations'] = true;
        $payload['categoriesCleanupRelations'] = true;

        $this->upsertEntity('product', $payload);
    }
}
