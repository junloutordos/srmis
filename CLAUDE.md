# SRMIS — Service Requests Management Information System (PSHS system-wide)

## Project Overview
SRMIS is a standalone, multi-tenant Laravel application extracted from the CRCMIS/BugSayMis monolith. It serves the Office of the Executive Director (OED) and all 16 PSHS campuses, each on its own subdomain with its own database schema.

**Modules:** MIS (IT Job Requests, ICT Equipment, ICT PMS, CSM Feedback), General Services (Vehicle / Facility / Work / Service / Messengerial requests, Assets), Data Management (Users, Roles & Permissions, Divisions, Offices, Org Structure, Buildings, Campuses, Rooms, Committees, Special Assignments), Approval Inbox, Reports (Audit Logs), Chat, Digital Signature.

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.4 (container; composer platform pinned to 8.4) |
| Multi-tenancy | stancl/tenancy v3 — schema-per-tenant, subdomain identification |
| Frontend | Vue 3 (`<script setup>`), Inertia.js 2, Tailwind CSS 3, Vite 7 |
| Database | MySQL 8.0 — one instance, schemas: `srmis_central` + `srmis_<campus>` |
| Real-time | Laravel Echo + Pusher protocol + Soketi |
| File Storage | AWS S3 — per-tenant key prefix `tenants/<slug>/` (never `disk('public')`) |
| Container | Docker (dev service name = `php`) |

## Multi-tenancy — how it works
- **Central domain** (`CENTRAL_DOMAIN`, dev: `srmis.localhost`) hosts the setup wizard (`/setup`, locked after install), central superadmin login, and the tenant admin panel (`/admin`). Routes in `routes/web.php`, bound to central domains via `Route::domain()`.
- **Tenant subdomains** (`oed.*`, `crc.*`, `src.*`, …) serve all module routes from `routes/tenant.php`, registered in `bootstrap/app.php` with `InitializeTenancyBySubdomain` + `PreventAccessFromCentralDomains` + `EnsureModuleEnabled`.
- **Tenant model**: `App\Models\Central\Tenant` — id = campus slug = subdomain = schema suffix (`srmis_crc`). Module toggles live in the `data` JSON (virtual `modules` attribute).
- **Provisioning**: `Tenant::create()` fires the pipeline CreateDatabase → MigrateDatabase (`database/migrations/tenant/`) → SeedDatabase (`TenantDatabaseSeeder`).
- **Tenant baseline schema**: `database/schema/srmis-tenant-tables.sql` (54 tables, extracted from the monolith), executed by the first tenant migration. **Never rename this file to `tenant-schema.sql`** — Laravel would treat it as a squashed schema dump for the `tenant` connection and try to load it with the `mysql` CLI.
- **Per-tenant isolation**: DB (schema), sessions (table inside tenant schema), cache (`PrefixCacheTenancyBootstrapper`, custom — prefix-based so the database store works), S3 (root override `tenants/%tenant%`), queue (stancl QueueTenancyBootstrapper).
- **Central auth**: guard `central` → `central_users` table → `App\Models\Central\CentralUser`. Tenant users never mix with central users.
- **Wizard-saved settings** (S3/WebSocket creds, encrypted at rest in `instance_settings`) are applied to runtime config by `InstanceConfigServiceProvider`.

## Infrastructure

### Development
```
/Users/junlou/srmis-docker/                # Docker Compose root
  src/srmis/                               # Laravel app (this repo)
Services: php, mysql (port 3310), nginx (8100), soketi (6004), phpmyadmin (8101)
Dev URLs:  http://srmis.localhost:8100         (central — wizard/admin)
           http://crc.srmis.localhost:8100     (tenant example)
```
Run artisan in dev:
```bash
cd /Users/junlou/srmis-docker && docker compose exec php sh -c "cd /var/www/html/srmis && php artisan COMMAND"
```
Tenant ops: `php artisan tenants:migrate`, `tenants:seed --class=TenantDatabaseSeeder`, `tenants:run`.

### Production (AWS — mirrors CRCMIS stack)
ECS Fargate (`srmis-prod` / `srmis-prod-service`, ap-southeast-1), single container (nginx + php-fpm + cron + queue worker via supervisord), ECR `srmis/app`, RDS MySQL 8.0, ElastiCache Redis, S3, SSM Parameter Store (`/srmis/prod/*`), Secrets Manager (`srmis/google-drive-credentials`), Cloudflare **wildcard** SSL (`*.srmis.pshs.edu.ph`), GitHub Actions deploy on push to `main`. Entry point runs central `migrate` then `tenants:migrate` on boot.

## Critical Rules (inherited from CRCMIS — still apply)
- **File uploads**: always base64 data URI in JSON body — Cloudflare WAF blocks multipart/form-data (403).
- **S3**: always `Storage::disk('s3')`; serve via `/media/{path}` proxy (`storage.proxy`), never direct S3 URLs or `temporaryUrl()`.
- **mPDF**: `sys_get_temp_dir()` for tempDir, never `storage_path()`.
- Never Blade for new pages (Inertia only), no TypeScript, no Pinia, Philippine locale for dates/currency, `git add` by name only, never force push main.
- Permission middleware: `permission:a|b` = ANY, `permission:a,b` = ALL. Use `$user->isSuperAdmin()` / `hasPermission()`.
- Soft delete = `status = 'inactive'` column.
- `divisions` table uses `division_name` (not `name`); `$user->division` is a legacy string column — use `Division::find($user->division_id)`.

## RBAC
- Tenant-scoped tables: `roles`, `permissions`, `role_user`, `permission_role`.
- Catalog: `TenantPermissionsSeeder` (+ `OrgStructurePermissionsSeeder`); grants: `TenantRolePermissionSeeder`; roles: `TenantRolesSeeder`.
- Roles: Administrator (superadmin bypass), MIS, DivisionChief, FAD Chief, GSU Head, OCD, Records, Faculty, Staff, Driver.
- Audit record of all gaps found/closed during extraction: `docs/RBAC-AUDIT.md`.
- Module gating: `EnsureModuleEnabled` infers module from path on every tenant request; explicit form `->middleware('module:chat')` also available.

## Key Gotchas discovered during extraction
- The monolith's migration history is **not** self-consistent with production: `users.emp_category`, `vehicle_requests.driver_id`, `it_job_requests.decline_reason/declined_at/ict_equipment_id`, `facility_requests.requestor/unit/email`, `work_requests.expected_completion_date/action_taken/date_completed`, and the whole `ict_equipments` / `ict_pms_equipment` tables exist only in prod. They are captured in the tenant baseline + `2026_06_12_000002_add_prod_drift_columns`.
- `ITJobCategory` model → table `it_job_requests_categories`; `ICTPMSHistory.php` declares class `IctPmsHistory` (PSR-4 warning, harmless, present in monolith).
- Email domain check accepts subdomains: `ALLOWED_EMAIL_DOMAIN=pshs.edu.ph` admits `@crc.pshs.edu.ph` etc.
- `AuditLogger` is a no-op outside an initialized tenant (central domain has no `audit_logs` table).
