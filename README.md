# Shopware SmartRelationSync Plugin

The goal of this Plugin is to ease the pain of dealing with relations in Shopware.

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
