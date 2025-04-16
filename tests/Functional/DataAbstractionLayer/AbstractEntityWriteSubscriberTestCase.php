<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

abstract class AbstractEntityWriteSubscriberTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    private const string PRODUCT_NUMBER = 'P384584';

    protected Context $context;

    private IdsCollection $ids;

    /**
     * @param array<non-empty-string, mixed> $payload
     */
    abstract protected function upsertProduct(array $payload): void;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
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

        $this->upsertProduct($builder->build());

        $builder = $this->createProductBuilder()
            ->category('Test 2');

        $this->upsertProduct($builder->build());

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

        $this->upsertProduct($payload);
    }
}
