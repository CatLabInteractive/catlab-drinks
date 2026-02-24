# CatLab Drinks - Development Instructions

## Project Overview
CatLab Drinks is a bar automation / POS system built with Laravel + Vue.js.
It has three frontend apps (Manage, POS, Client) sharing one Laravel backend.

## Building & Testing

### Frontend
```bash
npm install          # Install JS dependencies
npm run dev          # Development build
npm run watch        # Watch mode
npm run production   # Production build
```
Build uses Laravel Mix (`webpack.mix.js`). Output goes to `public/res/`.

### Backend
```bash
composer install                        # Install PHP dependencies (use --ignore-platform-reqs if PHP version mismatches)
php artisan migrate                     # Run database migrations
php artisan route:list                  # Verify routes are registered correctly
php artisan route:list --path=<prefix>  # Filter routes by path prefix
```

No automated test suite exists currently. Manual testing is required.
The lock file requires PHP ~8.1 or ~8.2; use `--ignore-platform-reqs` on newer PHP versions.

## Architecture

### Three Frontend Apps
| App     | URL      | Entry Point                       | Auth Method     |
|---------|----------|-----------------------------------|-----------------|
| Manage  | /manage/ | resources/manage/js/app.js        | OAuth (auth:api)|
| POS     | /pos/    | resources/pos/js/app.js           | Device token    |
| Client  | /order/  | resources/clients/js/app.js       | Public/token    |

### Two API Layers
| API          | Prefix       | Routes File                              | Auth            |
|--------------|--------------|------------------------------------------|-----------------|
| Management   | /api/v1/     | app/Http/ManagementApi/V1/routes.php     | OAuth (auth:api)|
| Device (POS) | /pos-api/v1/ | app/Http/DeviceApi/V1/routes.php         | auth:device     |

### Shared Namespace (`app/Http/Shared/V1/`)
Controllers and resource definitions that are used by **both** APIs live in the Shared namespace.
Each API creates a thin extending class in its own namespace for route resolution (because Charon
resolves controller names relative to the route collection's `namespace` setting).

**Pattern for sharing a controller between APIs:**
1. Put the full implementation in `App\Http\Shared\V1\Controllers\FooController`
2. Create `App\Http\ManagementApi\V1\Controllers\FooController extends \App\Http\Shared\V1\Controllers\FooController {}`
3. Create `App\Http\DeviceApi\V1\Controllers\FooController extends \App\Http\Shared\V1\Controllers\FooController {}`
4. Register routes in both `routes.php` files using `\App\Http\{Api}\V1\Controllers\FooController::setRoutes($routes)`

**Currently shared controllers:** EventController, MenuController, CategoryController, OrderController, OrderSummaryController

**Currently shared resource definitions:** OrderResourceDefinition, OrderSummaryResourceDefinition, OrderSummaryItemResourceDefinition

Resource definitions in the Shared namespace may reference definitions from ManagementApi (e.g., `MenuItemResourceDefinition`) — cross-namespace references are an established pattern.

### REST Framework: CatLab Charon
The backend uses CatLab Charon for REST API scaffolding:
- **Resource Definitions** (`app/Http/*/V1/ResourceDefinitions/`) define field visibility, writeability, and validation
- **Controllers** use `ChildCrudController` or `CrudController` traits from Charon
- Routes are registered via `static setRoutes(RouteCollection $routes)` on each controller
- The `childResource()` method auto-generates index/view/store/edit/destroy routes
- Use `'only'` parameter to restrict which routes are generated
- Route collections have a `namespace` property; controller action strings (e.g. `'OrderController@index'`) are resolved relative to it

**Charon edit/update flow** (used by `CrudController::edit()` and custom update endpoints):
1. Parse request body via `$this->resourceTransformer->fromInput($resourceDefinitionFactory, $writeContext, $request)`
2. Validate with `$inputResource->validate($writeContext, $entity)`
3. Apply writeable fields to entity via `$this->toEntity($inputResource, $writeContext, $existingEntity)`
4. Save via `$this->saveEntity($request, $entity)` which wraps in a DB transaction and calls `beforeSaveEntity()`/`afterSaveEntity()` hooks
5. Return via `$this->createViewEntityResponse($entity)`

**`beforeSaveEntity` / `afterSaveEntity` hooks:**
- Override these in controllers to add custom logic (e.g., validation, side effects)
- When using the CrudController trait, alias it: `use CrudController { beforeSaveEntity as traitBeforeSaveEntity; }`
- Call `$this->traitBeforeSaveEntity($request, $entity, $isNew)` to preserve the trait's default behavior
- Use `$entity->isDirty('field')` in `beforeSaveEntity` to check if a field changed before save
- Use `$entity->wasChanged('field')` in model `updated` events to check if a field actually changed after save

**Model events for side effects:**
- Prefer Eloquent model events (`created`, `updated`, etc.) over controller-level side effects for logic that should trigger regardless of which API endpoint or context modifies the model
- Register model events in the model's `boot()` or `booted()` method
- Use `wasChanged()` (not `isDirty()`) in `updated` events, since `isDirty()` is cleared after save

### Adding a New API Endpoint
1. Create or update the ResourceDefinition (in Shared if used by both APIs, otherwise in the specific API namespace)
2. Create or update the Controller using Charon traits (in Shared if used by both APIs)
3. If shared, create thin extending classes in both ManagementApi and DeviceApi namespaces
4. Register routes in the controller's `setRoutes()` method
5. Register routes in `routes.php` for each API that needs the endpoint
6. Create/update the Policy in `app/Policies/`
7. Register the policy in `AuthServiceProvider`

### Authorization Policies
Policies live in `app/Policies/` and extend `BasePolicy`.

**Key pattern — `$allowDevices` flag:**
- `BasePolicy::isMyEvent($user, $event, $allowDevices = false)` checks if the user/device belongs to the event's organisation
- When `$allowDevices = true`, both `User` and `Device` principals are accepted (uses `isDeviceOrUserPartOfOrganisation()`)
- When `$allowDevices = false` (default), only `User` principals are accepted (uses `isMyOrganisation()`)
- **Read-only** POS endpoints should pass `$allowDevices = true`
- **Write/admin** endpoints should use the default `false`

**Device self-management:**
- `DevicePolicy::view()` and `DevicePolicy::edit()` allow a device to view/edit itself (`$user->id === $device->id`)
- This enables POS devices to update their own settings (category filter, order preferences) via `PUT /pos-api/v1/devices/current`
- Always call `authorizeEdit($request, $entity)` or `authorizeView($request, $entity)` in device API endpoints

**Example:** `EventPolicy::orderSummary()` passes `$allowDevices = true` so POS devices can view sales summaries.

### Frontend Services
Frontend services extend `AbstractService` (`resources/shared/js/services/AbstractService.js`):
- Set `entityUrl` (singular resource path) and `indexUrl` (list path)
- Inherits `index()`, `get()`, `create()`, `update()`, `delete()` methods
- API base URL is `CATLAB_DRINKS_CONFIG.API_PATH`
  - For Manage: `/api/v1`
  - For POS: `/pos-api/v1`
- The same service classes are used by both Manage and POS — the base URL determines which API is called

### Frontend Routing
Vue routes are defined inline in each app's `app.js` (no separate router files).
Shared views from `resources/shared/js/views/` are imported by both apps.

**POS-specific views** live in `resources/pos/js/views/` (e.g., Headquarters, Menu, Authenticate, Events).
**Manage-specific views** live in `resources/manage/js/views/` (e.g., Devices, Events, Menu).

Both apps share the same route names for common features (e.g., `sales`, `summary`, `summary-names`).
This allows shared components to use `router-link` with named routes that work in either app context.

## Device Management

### POS Authentication Flow
POS devices don't use OAuth — they use device access tokens stored in localStorage:
- `catlab_drinks_device_pos_uid` — device UUID
- `calab_drinks_pos_api_identifier` — stripped API host (note: legacy typo in key name)
- `catlab_drinks_pos_api_url[identifier]` — full API base URL
- `catlab_drinks_pos_access_token[identifier]` — Bearer token

### Pairing Flow
1. Admin creates connect request → gets token + QR code
2. Device scans QR / pastes URL → calls `POST /api/v1/device-connect`
3. New device shows pairing code → admin enters it via `POST /device-connect-requests/{token}/pair`
4. Device receives access token → stored in localStorage

### 401 Handling
The POS Axios interceptor (`resources/pos/js/bootstrap.js`) clears all localStorage keys
and reloads on 401, returning the device to the pairing screen.

## Key Models
| Model                | Table                  | Purpose                          |
|----------------------|------------------------|----------------------------------|
| Device               | devices                | POS terminal registration        |
| DeviceAccessToken    | device_access_tokens   | Auth tokens for devices          |
| DeviceConnectRequest | device_connect_requests| Pairing session state            |
| Event                | events                 | Bar/event configuration          |
| MenuItem             | menu_items             | Products for sale                |
| Order                | orders                 | Customer orders                  |
| OrderItem            | order_items            | Line items within an order       |
| Organisation         | organisations          | Multi-tenant grouping            |
| User                 | users                  | Admin accounts                   |

## Common Patterns
- Vue components use Bootstrap-Vue (`b-*` components)
- Vue 3 compatibility mode via `@vue/compat`
- Shared components live in `resources/shared/js/`
- Services handle API communication, views handle UI
- The Settings view (`resources/shared/js/views/Settings.vue`) is shared between apps
- POS Events page has an Actions dropdown per event with links to Sales overview and Order history
- Manage Events page has a richer Actions dropdown including edit, delete, menu editing, attendees, etc.
- When adding POS access to an existing Manage-only feature: move controller + resource definitions to Shared, create thin stubs in both API namespaces, update the policy's `$allowDevices` flag, add frontend links
