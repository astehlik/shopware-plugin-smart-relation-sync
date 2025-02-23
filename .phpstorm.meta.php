<?php


namespace PHPSTORM_META {

    use Shopware\Core\Content\Rule\RuleCollection;

    override(\Symfony\Component\DependencyInjection\ContainerInterface::get(), map([
        'product.repository' => '\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Content\ProductProductCollection>',
        'rule.repository' => '\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\Content\Rule\RuleCollection>',
        'tax.repository' => '\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository<\Shopware\Core\System\Tax\TaxCollection>',
        //'tax.repository' => EntityRepository::class . '<' . TaxCollection::class . '>',
        '' => '@',
    ]));
}
