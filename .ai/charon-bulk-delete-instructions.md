# Charon Framework: Bulk Delete Support

## Background

The CatLab Charon framework (`catlabinteractive/charon-laravel`) currently supports bulk **create** operations
through the `store()` method in `CrudController`. When the JSON body contains an `items` array, Charon
processes each item as a separate resource and returns a collection response.

However, there is **no built-in bulk delete** support. The `destroy()` method in `CrudController` only
handles a single entity identified by the route parameter.

## Current Workaround

In the `catlab-drinks` project, bulk delete is implemented as a custom controller method:

```php
// AttendeeController.php
public function bulkDestroy(Request $request)
{
    $event = $this->getParent($request);
    $ids = $request->json('ids', []);

    $event->attendees()
        ->whereIn('id', $ids)
        ->delete();

    return \Response::json(['success' => true]);
}
```

With a custom route:
```php
$childResource->delete('events/{parentId}/attendees', 'AttendeeController@bulkDestroy')
    ->summary('Bulk delete attendees')
    ->parameters()->post('ids');
```

The frontend sends:
```
DELETE /api/v1/events/{id}/attendees
X-Bulk-Request: 1
Content-Type: application/json

{ "ids": [1, 2, 3] }
```

## Proposed Charon Framework Extension

### 1. Add `bulkDestroy()` method to `CrudController` trait

Add a new method in `vendor/catlabinteractive/charon-laravel/src/Controllers/CrudController.php`:

```php
/**
 * Bulk delete entities by IDs.
 * @param Request $request
 * @return Response
 * @throws AuthorizationException
 */
public function bulkDestroy(Request $request)
{
    $this->request = $request;

    $ids = $request->json('ids', []);
    if (!is_array($ids) || empty($ids)) {
        return $this->toResponse([
            'error' => [
                'message' => 'No IDs provided for bulk delete.'
            ]
        ])->setStatusCode(400);
    }

    $deletedCount = 0;
    foreach ($ids as $id) {
        $entity = $this->callEntityMethod($request, 'find', $id);
        if ($entity) {
            $this->authorizeDestroy($request, $entity);
            $entity->delete();
            $deletedCount++;
        }
    }

    return $this->toResponse([
        'success' => true,
        'deleted' => $deletedCount
    ]);
}
```

### 2. Add `bulkDestroy()` method to `ChildCrudController` trait

Override for child resources to scope deletion to the parent in
`vendor/catlabinteractive/charon-laravel/src/Controllers/ChildCrudController.php`:

```php
/**
 * Bulk delete child entities by IDs.
 * @param Request $request
 * @return Response
 * @throws AuthorizationException
 */
public function bulkDestroy(Request $request)
{
    $this->request = $request;

    $ids = $request->json('ids', []);
    if (!is_array($ids) || empty($ids)) {
        return $this->toResponse([
            'error' => [
                'message' => 'No IDs provided for bulk delete.'
            ]
        ])->setStatusCode(400);
    }

    $relationship = $this->getRelationship($request);
    $entities = $relationship->whereIn('id', $ids)->get();

    $deletedCount = 0;
    foreach ($entities as $entity) {
        $this->authorizeDestroy($request, $entity);
        $entity->delete();
        $deletedCount++;
    }

    return $this->toResponse([
        'success' => true,
        'deleted' => $deletedCount
    ]);
}
```

### 3. Register the route in `childResource()`

In the route builder, when `'destroy'` is in the `'only'` list, also register a bulk delete route.
In `RouteCollection::childResource()` (or wherever routes are generated), add:

```php
// If destroy is enabled, also add bulk destroy
if (in_array('destroy', $only)) {
    $routes->delete($path, $controllerName . '@bulkDestroy')
        ->summary('Bulk delete ' . $resourceName)
        ->parameters()->post('ids');
}
```

### 4. Convention: `X-Bulk-Request` header

To distinguish bulk from single requests on the same endpoint, the convention is:
- Single entity operations use route parameters (e.g., `DELETE /attendees/{id}`)
- Bulk operations use the collection endpoint with `X-Bulk-Request: 1` header
- The body contains an `ids` array for bulk delete, or an `items` array for bulk create

### 5. Frontend usage pattern

```javascript
// Bulk create
service.client({
    method: 'post',
    url: 'events/' + eventId + '/attendees',
    data: { items: [...] },
    headers: { 'X-Bulk-Request': '1' }
});

// Bulk delete
service.client({
    method: 'delete',
    url: 'events/' + eventId + '/attendees',
    data: { ids: [1, 2, 3] },
    headers: { 'X-Bulk-Request': '1' }
});
```
