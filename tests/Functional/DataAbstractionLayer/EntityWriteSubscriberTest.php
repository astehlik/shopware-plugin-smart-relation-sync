<?php

declare(strict_types=1);

namespace Swh\SmartRelationSync\Tests\Functional\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

final class EntityWriteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const string PRODUCT_NUMBER = 'P384584';

    private Context $context;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();
    }

    public function testSyncManyToMany(): void
    {
        $builder = $this->createProductBuilder()
            ->category('Test 1');

        $this->upsertProductWithRelationCleanup($builder);

        $builder = $this->createProductBuilder()
            ->category('Test 2');

        $this->upsertProductWithRelationCleanup($builder);

        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('categories');

        $product = $this->searchProductSingle($criteria);
        $categories = $product->getCategories();
        self::assertCount(1, $categories ?? []);
        self::assertSame($this->ids->get('Test 2'), $categories?->first()?->getId());
    }

    public function testSyncOneToMany(): void
    {
        $productBuilder = $this->createProductBuilder()
            ->prices('test', 14.28);

        $this->upsertProductWithRelationCleanup($productBuilder);

        $productBuilder = $this->createProductBuilder()
            ->prices('test2', 115.0);

        $this->upsertProductWithRelationCleanup($productBuilder);

        $criteria = new Criteria([$this->ids->get(self::PRODUCT_NUMBER)]);
        $criteria->addAssociation('prices');

        $product = $this->searchProductSingle($criteria);
        $prices = $product->getPrices();
        self::assertCount(1, $prices ?? []);
        self::assertSame($this->ids->get('test2'), $prices?->first()?->getRuleId());
    }

    private function createProductBuilder(): ProductBuilder
    {
        return (new ProductBuilder($this->ids, self::PRODUCT_NUMBER))
            ->name('Test product')
            ->price(11.5);
    }

    /**
     * @param array<mixed> $payload
     */
    private function executeUpsert(string $entity, array $payload): void
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

    private function upsertProductWithRelationCleanup(ProductBuilder $builder): void
    {
        $payload = $builder->build();

        $payload['pricesCleanupRelations'] = true;
        $payload['categoriesCleanupRelations'] = true;

        $this->executeUpsert('product', $payload);
    }
}
