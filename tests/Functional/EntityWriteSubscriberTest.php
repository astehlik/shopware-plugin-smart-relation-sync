<?php

namespace Swh\SmartRelationSync\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityWriteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testSyncManyToMany()
    {
        $category1Id = Uuid::fromStringToHex('category-1');
        $category2Id = Uuid::fromStringToHex('category-2');

        $productId = Uuid::fromStringToHex('product-1');

        $this->executeUpsert('category', ['id' => $category1Id, 'name' => 'Test 1']);
        $this->executeUpsert('category', ['id' => $category2Id, 'name' => 'Test 2']);

        $this->createProduct($productId, ['categories' => [['id' => $category1Id]]]);

        $this->executeUpsert(
            'product',
            [
                'id' => $productId,
                'categories' => [['id' => $category2Id]],
                'categoriesCleanupRelations' => true,
            ]
        );

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('categories');

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')->search($criteria, $this->context)->first();
        $categories = $product->getCategories();
        self::assertCount(1, $categories);
        self::assertSame($category2Id, $categories->first()->getId());
    }

    public function testSyncOneToMany()
    {
        $productId = Uuid::fromStringToHex('product-1');

        $prices = [
            [
                'id' => Uuid::fromStringToHex('product-1-price-1'),
                'quantityStart' => 1,
                'price' => [new Price(Defaults::CURRENCY, 12.0, 14.28, true)],
                'ruleId' => $this->getDefaultRuleId(),
            ],
        ];

        $this->createProduct($productId, ['prices' => $prices]);

        $price2Id = Uuid::fromStringToHex('product-2-price-2');

        $prices = [
            [
                'id' => $price2Id,
                'quantityStart' => 1,
                'price' => [new Price(Defaults::CURRENCY, 100.0, 119.0, true)],
                'ruleId' => $this->getDefaultRuleId(),
            ],
        ];

        $payload = [
            'id' => $productId,
            'prices' => $prices,
            'pricesCleanupRelations' => true,
        ];

        $this->executeUpsert('product', $payload);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        /** @var ProductEntity $product */
        $product = $this->getContainer()->get('product.repository')->search($criteria, $this->context)->first();
        $prices = $product->getPrices();
        self::assertCount(1, $prices);
        self::assertSame($price2Id, $prices->first()->getId());
    }

    private function createProduct(string $productId, array $additionalPayload): void
    {
        $payload = [
            'id' => $productId,
            'productNumber' => 'P384584',
            'taxId' => $this->getDefaultTaxRateId(),
            'stock' => 10,
            'name' => 'Test product',
            'price' => [new Price(Defaults::CURRENCY, 10.0, 11.9, true)],
            'pricesCleanupRelations' => true,
        ];

        $this->executeUpsert('product', array_merge($payload, $additionalPayload));
    }

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
            ]
        );

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());
    }

    private function getDefaultRuleId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', 'All customers'));

        return $this->getContainer()->get('rule.repository')->searchIds($criteria, $this->context)->firstId();
    }

    private function getDefaultTaxRateId(): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('position', 1));

        return $this->getContainer()->get('tax.repository')->searchIds($criteria, $this->context)->firstId();
    }
}
