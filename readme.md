CatLab Drinks
=============
A bar automation / point-of-sale (POS) web application with support for NFC cashless topup cards,
remote ordering, and multi-device management.

Online at [https://drinks.catlab.eu](https://drinks.catlab.eu).

Deploy
------
Deploy your own instance with a single click:

[![Deploy to Heroku](https://www.herokucdn.com/deploy/button.svg)](https://heroku.com/deploy?template=https://github.com/CatLabInteractive/catlab-drinks)

[![Deploy to DO](https://www.deploytodo.com/do-btn-blue.svg)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/CatLabInteractive/catlab-drinks/tree/master)

> **DigitalOcean note:** You will be prompted to set the `APP_KEY` secret. Generate one with
> `php -r "echo 'base64:'.base64_encode(random_bytes(32));"` and paste the result.

Architecture
------------

### Applications
The project consists of three separate Vue.js frontend applications sharing a common Laravel backend:

- **Manage** (`/manage/`) — Admin panel for managing events, menus, devices, financial overviews, and settings.
  Built from `resources/manage/js/app.js`. Uses OAuth authentication (`auth:api`).

- **POS** (`/pos/`) — Point-of-sale terminal for bartenders. Handles live orders, remote order processing,
  NFC card topups, and cash payments. Built from `resources/pos/js/app.js`. Uses device token authentication
  (`auth:device`).

- **Client / Order** (`/order/`) — Customer-facing order form for remote ordering. Built from
  `resources/clients/js/app.js`.

### APIs
- **Management API** (`/api/v1/`) — RESTful API for the Manage app. Uses [CatLab Charon](https://github.com/CatLab/charon-laravel)
  for resource definitions, routing, and serialization. Protected by OAuth (`auth:api`).

- **Device API** (`/pos-api/v1/`) — RESTful API for POS devices. Protected by device access tokens (`auth:device`).

### Key Technologies
- **Backend:** Laravel (PHP), CatLab Charon REST framework
- **Frontend:** Vue 3 (via `@vue/compat`), Bootstrap-Vue, Laravel Mix (Webpack)
- **Authentication:** Laravel Passport (OAuth) for management, custom device tokens for POS
- **Build:** `npm run dev` / `npm run production` via Laravel Mix

Device Management & Pairing
----------------------------
POS devices authenticate independently of user accounts. This ensures POS terminals stay operational
even when an admin's management session expires.

### Pairing Flow
1. **Admin** opens Manage → Devices → clicks "Pair a device"
2. A connect request is created, generating a QR code containing
   `https://drinks.catlab.eu/connect?data={BASE64}` (where BASE64 = `{api, token}`)
3. **POS device** scans the QR code or pastes the URL/token manually
4. If the device is new, it generates a **pairing code** shown on screen
5. Admin enters the pairing code and a device name in the Manage panel
6. The device receives a permanent access token and is ready to use

### Device API Authentication
Devices receive access tokens stored in localStorage:
- `catlab_drinks_device_pos_uid` — Unique device ID
- `calab_drinks_pos_api_identifier` — API host identifier
- `catlab_drinks_pos_api_url[identifier]` — Full API URL
- `catlab_drinks_pos_access_token[identifier]` — Bearer token

On 401 responses, all keys are cleared and the device returns to the pairing screen.

Setup with Docker
-----------------
The easiest way to get started is with Docker Compose:

```bash
docker-compose up
```

That's it! On the first run this will automatically:
- Copy `.env.example` to `.env` (if no `.env` exists)
- Generate an application key
- Install Composer and NPM dependencies
- Run database migrations
- Generate Passport encryption keys
- Build the frontend assets

Once the containers are running, the application is available at [http://localhost:8095](http://localhost:8095).

> **WARNING:** The application key (in `.env`) encrypts secrets in the database and NFC card data.
> Losing this key makes existing NFC cards unusable. **Back it up immediately.**

### Manual Setup (without Docker)
1. `composer install` — install PHP dependencies
2. Copy `.env.example` to `.env` and fill in database credentials
3. `php artisan key:generate` — create application key
4. `php artisan migrate` — initialize the database
5. `php artisan passport:keys` — generate OAuth encryption keys
6. `npm install` — install JS dependencies
7. `npm run production` — compile frontend assets

You should now be able to register an account at the website.

Development
-----------
- `npm run dev` — compile assets for development
- `npm run watch` — watch for file changes and recompile
- Frontend source: `resources/{manage,pos,clients}/js/`
- Shared code: `resources/shared/js/`
- SCSS: `resources/{manage,pos,clients}/sass/`

### Project Structure
```
app/
├── Http/
│   ├── ManagementApi/V1/       # Management API (controllers, resource definitions, routes)
│   ├── DeviceApi/V1/           # POS Device API
│   ├── Shared/V1/              # Shared controllers and resource definitions used by both APIs
│   │   ├── Controllers/        # OrderController, OrderSummaryController, EventController, etc.
│   │   └── ResourceDefinitions/# OrderResourceDefinition, OrderSummaryResourceDefinition, etc.
│   └── Middleware/
├── Models/                     # Eloquent models
├── Policies/                   # Authorization policies
└── Providers/

resources/
├── manage/js/                  # Manage app (Vue components, services, views)
├── pos/js/                     # POS app (Vue components, services, views)
├── clients/js/                 # Client order app
├── shared/js/                  # Shared Vue components and services
│   ├── services/               # AbstractService, EventService, SettingService, etc.
│   ├── nfccards/               # NFC card reader integration
│   └── views/                  # Shared views (Sales, SalesSummary, Cards, Settings, etc.)
└── sass/                       # SCSS stylesheets
```

### API Patterns (CatLab Charon)
- Controllers extend `ResourceController` and use `ChildCrudController` or `CrudController` traits
- Resource definitions (`*ResourceDefinition.php`) define field visibility, writeability, and validation
- Routes are registered via static `setRoutes(RouteCollection $routes)` methods
- Authorization is handled by policies (`app/Policies/`); use `$allowDevices = true` in `isMyEvent()` for read-only POS access

### Sharing Controllers Between APIs
Controllers and resource definitions used by both Management and Device APIs live in `App\Http\Shared\V1\`.
Each API creates a thin extending class in its own namespace (required because Charon resolves controller
names relative to the route collection's `namespace` setting):

```php
// App\Http\DeviceApi\V1\Controllers\FooController.php
class FooController extends \App\Http\Shared\V1\Controllers\FooController {}
```

NFC Cashless Topup
------------------
To use the NFC topup system, connect an ACR122U card reader and install the
[NFC Socket.IO service](https://github.com/catlab-drinks/nfc-socketio).

Alternatively, you can use the [android app](https://play.google.com/store/apps/details?id=eu.catlab.drinks).

### Topup Domain
NFC cards contain a short URL that links to a card-specific topup page. To keep the URL as short as possible
(due to limited storage on NFC cards), you can configure a dedicated short domain that automatically redirects
to the topup page.

Set the `TOPUP_DOMAIN_NAME` environment variable to your short domain:

```
TOPUP_DOMAIN_NAME=d.ctlb.eu
```

When a request comes in from this domain (e.g., `https://d.ctlb.eu/{cardId}`), the middleware will automatically
redirect it to `/topup/{cardId}`. This allows NFC cards to store very short URLs while still directing users
to the correct topup page.

The configuration supports multiple domains if needed (configured in `config/app.php`).

Dokku
-----
Be sure to set public and private OAuth keys in environment variables.
