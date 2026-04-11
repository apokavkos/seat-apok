# SeAT 5 AI Operational Context (SeAT-Apok)

This file provides mandatory instructions for AI agents maintaining this SeAT v5 installation and its custom plugins.

## 1. Critical Operational Rules (Server Stability)
- **Cache Management**: After ANY change to plugins, routes, or sidebars, you MUST run:
  `php artisan config:clear`, `route:clear`, `view:clear`, and `cache:clear`.
  *Failure to do this results in immediate 500 Internal Server Errors.*
- **Provider Loading Order**: Custom providers (Dashboard & Importing) **MUST** be registered in `config/app.php` **BEFORE** the `WebServiceProvider`. This ensures permissions are correctly injected into the Laravel Gate.
- **Sidebar Validation**: Every sidebar menu and entry MUST have a `label` key. If `label` is missing, the sidebar renderer will silently fail to display the menu.

## 2. Technical Standards
- **Shell File Edits**: Always use single-quoted heredocs (`<<'EOF'`) when writing PHP files via the terminal to prevent the host shell from mangling PHP variables (e.g., `$request`).
- **ESI Client**: Do not instantiate raw `Eseye` objects. Always resolve the `Seat\Services\Contracts\EsiClient` from the Laravel container to ensure proper authentication and PSR-18 HTTP client bindings.
- **Database Precision**: Use `double` or `decimal` for market metrics (Markup %, Profit) to prevent "Numeric value out of range" errors when dealing with low-price Jita items.

## 3. Plugin Logic
- **Background Cache Warming**: The Market Analysis dashboard is pre-calculated. If data looks stale, run:
  `php artisan seat:importing:import --download --simulate`
- **Hub Discovery**: The import engine dynamically finds Region IDs based on the `solar_system_id` or `structure_id` defined in the Market Hub settings.
