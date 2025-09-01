<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
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
use Swh\SmartRelationSync\Tests\Compatibility\IdsCollection;
use Swh\SmartRelationSyncTestPlugin\Entity\VersionedChildCollection;
use Swh\SmartRelationSyncTestPlugin\Entity\VersionedParentCollection;

abstract class AbstractEntityWriteSubscriberTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    private const PRODUCT_NUMBER = 'P384584';

    private const VERSIONED_PARENT_PAYLOAD = [
        'id' => 'c56368909789d4f61662603c97687a97',
        'name' => 'Parent test',
        'children' => [
            [
                'id' => 'a9742e0d21d75726412c3e32cf9c08fa',
                'name' => 'Child test 1',
            ],
            [
                'id' => '063595fd7ed5d047f29c83214fa967b8',
                'name' => 'Child test 2',
            ],
        ],
        'childrenCleanupRelations' => true,
    ];

    protected Context $context;

    private IdsCollection $ids;

    /**
     * @param non-empty-string $entity
     * @param array<non-empty-string, mixed> $payload
     */
    abstract protected function upsertEntity(string $entity, array $payload): void;

    protected function setUp(): void
    {
        $this->context = Context::createCLIContext();
        $this->ids = new IdsCollection();
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

    #[DataProvider('provideSyncManyToManyExtensionCases')]
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

    /**
     * @return array<array{0: bool}>
     */
    public static function provideSyncManyToManyExtensionCases(): iterable
    {
        return [
            [true],
            [false],
        ];
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

    public function testSyncOneToManyKeepsExisting(): void
    {
        $productBuilder = $this->createProductBuilder()
            ->prices('test', 14.28);

        $this->upsertProductWithRelationCleanup($productBuilder->build());

        $productBuilder = $productBuilder
            ->prices('test2', 115.0);

        $this->upsertProductWithRelationCleanup($productBuilder->build());

        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('prices');

        $product = $this->searchProductSingle($criteria);
        $prices = $product->getPrices() ?? new ProductPriceCollection();
        self::assertCount(2, $prices);

        $price1Id = Uuid::fromStringToHex($this->ids->get('test'));
        self::assertSame(14.28, $prices->get($price1Id)?->getPrice()->first()?->getGross());

        $price2Id = Uuid::fromStringToHex($this->ids->get('test2'));
        self::assertSame(115.0, $prices->get($price2Id)?->getPrice()->first()?->getGross());
    }

    public function testSyncOneToManyWithVersioningKeepsExisting(): void
    {
        $this->upsertEntity('versioned_parent', self::VERSIONED_PARENT_PAYLOAD);

        $this->assertVersionedParentChildrenCount(2);

        $this->upsertEntity('versioned_parent', self::VERSIONED_PARENT_PAYLOAD);

        $this->assertVersionedParentChildrenCount(2);
    }

    public function testSyncOneToManyWithVersioningReplacesEverything(): void
    {
        $payload = self::VERSIONED_PARENT_PAYLOAD;

        $this->upsertEntity('versioned_parent', $payload);

        $this->assertVersionedParentChildrenCount(2);

        $newId = Uuid::randomHex();

        $payload['children'][0]['id'] = $newId;
        $this->upsertEntity('versioned_parent', $payload);

        $children = $this->assertVersionedParentChildrenCount(2);
        self::assertSame($newId, $children->first()?->getId());
    }

    protected function loadCategories(): ?CategoryCollection
    {
        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('categories');

        $product = $this->searchProductSingle($criteria);
        return $product->getCategories();
    }

    private function assertVersionedParentChildrenCount(int $expectedCount): VersionedChildCollection
    {
        $criteria = new Criteria(['c56368909789d4f61662603c97687a97']);
        $criteria->addAssociation('children');

        $result = $this->getVersionedParentRepository()->search($criteria, $this->context)->first()?->getChildren();

        self::assertInstanceOf(VersionedChildCollection::class, $result);

        self::assertCount($expectedCount, $result);

        return $result;
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

    /**
     * @return EntityRepository<VersionedParentCollection>
     */
    private function getVersionedParentRepository(): EntityRepository
    {
        /** @var EntityRepository<VersionedParentCollection> $productRepository */
        $productRepository = $this->getContainer()->get('versioned_parent.repository');
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

        foreach ($payload['prices'] ?? [] as $key => $price) {
            $payload['prices'][$key]['id'] = Uuid::fromStringToHex($price['rule']['id']);
        }

        $this->upsertEntity('product', $payload);
    }
}
