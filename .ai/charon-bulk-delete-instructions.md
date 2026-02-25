# Charon Framework: Bulk Delete Support

## Status: Implemented in Charon 1.8.0

Bulk delete support has been implemented in `catlabinteractive/charon-laravel` v1.8.0 and `catlabinteractive/charon` v1.8.0.

### How it works

The `ChildCrudController` and `CrudController` traits now include a `bulkDestroy()` method. The route is automatically registered when `destroy` is included in the `only` options of `childResource()` or `resource()`.

### Route

```
DELETE /events/{parentId}/attendees
```

### Request format

The body should contain resource identifiers in the standard Charon `items` format:

```json
{
    "items": [
        { "id": 1 },
        { "id": 2 },
        { "id": 3 }
    ]
}
```

### Response format

```json
{
    "success": true,
    "deleted": 3
}
```

### Known issue in v1.8.0

The `mergeOptions()` method in `RouteProperties.php` has overly strict type hints (`string $a, string $b`) that should be `string|array $a, string|array $b` to handle middleware arrays. This causes a runtime error when routes use middleware arrays. This needs to be fixed upstream.
