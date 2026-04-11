# Deployment Guide: SeAT-Apok Plugins

This repository contains the `seat-dashboard` and `seat-importing` plugins for SeAT v5. Follow these steps to deploy them onto a fresh SeAT installation.

## 1. Clone the Plugins
Clone this repository into your SeAT `packages` directory:
```bash
# On the host:
cd /opt/seat/packages
git clone https://github.com/apokavkos/seat-apok.git .
```

## 2. Register Namespaces (composer.json)
Edit your SeAT `composer.json` (in the root SeAT directory) and add these lines to the `autoload -> psr-4` section:
```json
"Apokavkos\\SeatDashboard\\": "packages/seat-dashboard/src/",
"Apokavkos\\SeatImporting\\": "packages/seat-importing/src/"
```
Then run:
```bash
docker exec seat-front-1 composer dump-autoload
```

## 3. Register Providers (config/app.php)
Edit `config/app.php` and add these providers **BEFORE** the `WebServiceProvider`:
```php
Apokavkos\SeatImporting\SeatImportingServiceProvider::class,
Apokavkos\SeatDashboard\SeatDashboardServiceProvider::class,
```
*Note: They MUST load before WebServiceProvider to ensure permissions register in the Laravel Gate.*

## 4. Initialize Database & Cache
Run these commands to apply the migrations and clear the cache:
```bash
docker exec seat-front-1 php artisan migrate
docker exec seat-front-1 php artisan config:clear
docker exec seat-front-1 php artisan route:clear
docker exec seat-front-1 php artisan view:clear
docker exec seat-front-1 php artisan cache:clear
```

## 5. Core Patches (Mandatory for SSL/SSO)
If this is a fresh Docker install, ensure:
1. `acme.json` permissions are `600`.
2. Apply the **EVE Socialite Patch** to `vendor/eveseat/services/src/Socialite/EveOnlineProvider.php` to prevent 500 errors on login (fallback `scp` to empty array).

## 6. Permissions
Log in as an admin and ensure your user has the following permissions:
- `seat-dashboard.view`
- `market.import`
- `market.settings`
