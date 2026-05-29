# SeAT 5 AI Operational Context (SeAT-Apok)

This file provides mandatory instructions for AI agents maintaining this SeAT v5 installation and its custom plugins.

## 1. Critical Operational Rules (Server Stability)
- **Cache Management**: After ANY change to plugins, routes, or sidebars, you MUST run:
  `php artisan config:clear`, `route:clear`, `view:clear`, and `cache:clear`.
  *Failure to do this results in immediate 500 Internal Server Errors.*
- **Provider Loading Order**: Custom providers (Dashboard & Importing) **MUST** be registered in `config/app.php` **BEFORE** the `WebServiceProvider`. This ensures permissions are correctly injected into the Laravel Gate.
- **Sidebar Validation**: Every sidebar menu and entry MUST have a `label` key. If `label` is missing, the sidebar renderer will silently fail to display the menu.

## 2. Living Documentation & Evolution (Mandatory)
- **Knowledge Capture**: If you (the AI) discover a new server-specific quirk, fix a persistent 500 error, or identify a new dependency, you **MUST** append that knowledge to this file or `AI_CONTEXT.md` before ending the session.
- **Root Cause Tracking**: Document "Why" a failure happened (e.g., "Fuzzwork CSV format changed to pipe-delimited") to prevent future agents from repeating the same investigation.

## 3. Technical Standards
- **Shell File Edits**: Always use single-quoted heredocs (`<<'EOF'`) when writing PHP files via the terminal to prevent mangling PHP variables.
- **ESI Client**: Always resolve `Seat\Services\Contracts\EsiClient` from the container. Do not use raw `Eseye` objects.
- **Database Precision**: Use `double` for all market metrics to prevent out-of-range errors.

## 4. Plugin Logic
- **Background Cache Warming**: Dashboard tables are pre-calculated. Re-run `php artisan seat:importing:import --download --simulate` if data is stale.
- **Hub Discovery**: Import engine dynamically finds Region IDs via `solar_system_id` or `structure_id`.

## 5. Troubleshooting History & Quirk Fixes
- **Dashboard View Path Collision (500 View Not Found)**:
  - *Quirk*: `DashboardController::index` returned `'seat-dashboard::index'`. But the blade view was stored under `src/resources/views/dashboard/index.blade.php`.
  - *Fix*: Changed the controller returned view string to `'seat-dashboard::dashboard.index'`. Always match the exact nested directory naming in custom views.
- **Undefined Method & Mismatched Columns in Dashboard**:
  - *Quirk*: `DashboardController.php` called `getSystemIndustryData()` which was undefined, plucked `system_id` which was named `solar_system_id`, and called `updateOrCreate` on invalid columns.
  - *Fix*: Re-routed to `getSystemCostIndexes()`, mapped indices dynamically to model instances, and corrected model operations to match DB schema.
