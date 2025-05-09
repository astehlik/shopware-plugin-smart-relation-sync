# Shopware SmartRelationSync Plugin

The goal of this Plugin is to ease the pain of dealing with relations in Shopware.

## Shopware native

Before explaining the solution in this Plugin, please be aware that there is a Shopware native way in the Sync-API
to archive the same result. For example to assign a new category, you can send the following HTTP request:

```http request
POST /api/_action/sync
Content-Type: application/json

{
    "change-category": {
        "entity": "product",
        "action": "upsert",
        "payload": [
            {
                "id": "<the product id>",
                "categories": [
                    {"id": "<the new category id 1>"}
                    {"id": "<the new category id 2>"}
                ]
            }
        ]
    },
    "delete-obsolete": {
        "entity": "product_category",
        "action": "delete",
        "criteria": [
            {
                "type": "equals",
                "field": "productId",
                "value": "<the product id>"
            },
            {
                "type": "not",
                "operator": "and",
                "queries": [
                    {
                        "type": "equalsAny",
                        "field": "categoryId",
                        "value": ["<the new category id 1>", "<the new category id 2>"]
                    }
                ]
            }
        ]
    }
}
```

## Quick start

After installing the Plugin, you can enable automatic relation cleanup in
the DAL or in the Sync-API, for example:

```php
$productData = [
    'id' => '...',
    'categories' => [['id' => '...']],
    'categoriesCleanupRelations' => true,
];
$this->productRepository->upsert([$productData], $context);
```

```http request
POST /api/_action/sync
Content-Type: application/json

{
    "write-product": {
        "entity": "product",
        "action": "upsert",
        "payload": [
            {
                "id": "...",
                "categories": [{ "id": "..." }],
                "categoriesCleanupRelations": true
            }
        ]
    }
}
```

By setting `categoriesCleanupRelations` to `true`, the plugin will automatically
remove all category relations that are not in the given array.

You can do this for any many-to-many or one-to-many relation with the `CleanupRelation` suffix.
