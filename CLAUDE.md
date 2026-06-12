# SRMIS — Service Requests Management Information System (PSHS system-wide)

## Project Overview
SRMIS is a standalone, multi-tenant Laravel application extracted from the CRCMIS/BugSayMis monolith. It serves the Office of the Executive Director (OED) and all 16 PSHS campuses on ONE shared domain — the campus is detected from the user's email at login — with each campus isolated in its own database schema.

**Modules:** MIS (IT Job Requests, ICT Equipment, ICT PMS, CSM Feedback), General Services (Vehicle / Facility / Work / Service / Messengerial requests, Assets), Data Management (Users, Roles & Permissions, Divisions, Offices, Org Structure, Buildings, Campuses, Rooms, Committees, Special Assignments), Approval Inbox, Reports (Audit Logs), Chat, Digital Signature.

## Tech Stack
| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.4 (container; composer platform pinned to 8.4) |
| Multi-tenancy | stancl/tenancy v3 — schema-per-tenant, single domain, login-based campus detection |
| Frontend | Vue 3 (`<script setup>`), Inertia.js 2, Tailwind CSS 3, Vite 7 |
| Database | MySQL 8.0 — one instance, schemas: `srmis_central` + `srmis_<campus>` |
| Real-time | Laravel Echo + Pusher protocol + Soketi |
| File Storage | AWS S3 — per-tenant key prefix `tenants/<slug>/` (never `disk('public')`) |
| Container | Docker (dev service name = `php`) |

## Multi-tenancy — how it works (single domain)
- **One domain for everyone** (e.g. `srmis.pshs.edu.ph`; dev `localhost:8100`). No subdomains, no wildcard SSL. The campus is detected from the email at sign-in: `@<slug>.pshs.edu.ph` → that campus, `@pshssystem.edu.ph` → OED (`App\Tenancy\CampusEmailMapper`; configurable via `CAMPUS_EMAIL_BASE_DOMAIN` / `OED_EMAIL_DOMAIN`).
- **Per-request resolution** (`App\Tenancy\ResolveTenant`, in the web group): ① session `tenant_id` binding set at login (authoritative), ② `?tenant=` on signed email links (covered by the signature; honored only when valid), ③ login-POST email inference. No tenant → request continues as guest.
- **Middleware priority is load-bearing**: `bootstrap/app.php` defines `$middleware->priority([...])` so the order is StartSession → ResolveTenant → Authenticate → ValidateSignature → SubstituteBindings. Without it, Laravel's sorter runs `auth` (user lookup) before tenancy resolution → "srmis_central.users doesn't exist"; and route binding before signature checks → 500 instead of 403 on tampered links.
- **Sessions are CENTRAL** (`config/session.php` connection `central`): one sessions table in `srmis_central`, since the session must load before the tenant is known. The session itself carries the campus binding.
- **Signed links**: `App\Tenancy\TenantAwareUrlGenerator` (bound in `AppServiceProvider::register`) appends `tenant=<slug>` to every `signedRoute()`/`temporarySignedRoute()` automatically — all monolith mail code works untouched.
- **Central plane**: `/setup` wizard (404s after install) + `/system/*` superadmin panel (guard `central` → `central_users` → `App\Models\Central\CentralUser`). Tenant RBAC pages keep `/admin/*`. `HandleInertiaRequests` shares central users as guest (they have no roles relations) and shares `campus` ({id,name,code}) for the sidebar badge.
- **Tenant model**: `App\Models\Central\Tenant` — id = campus slug = schema suffix (`srmis_crc`) = email subdomain. Module toggles live in the `data` JSON (virtual `modules` attribute; domains table unused).
- **Provisioning**: `Tenant::create()` fires CreateDatabase → MigrateDatabase (`database/migrations/tenant/`) → SeedDatabase (`TenantDatabaseSeeder`).
- **Tenant baseline schema**: `database/schema/srmis-tenant-tables.sql` (54 tables, extracted from the monolith), executed by the first tenant migration. **Never rename this file to `tenant-schema.sql`** — Laravel would treat it as a squashed schema dump for the `tenant` connection and try to load it with the `mysql` CLI.
- **Per-tenant isolation**: DB (schema), cache (custom `PrefixCacheTenancyBootstrapper` — prefix-based so the database store works), S3 (root override `tenants/%tenant%`), queue (stancl QueueTenancyBootstrapper).
- **Wizard-saved settings** (S3/WebSocket creds, encrypted at rest in `instance_settings`) are applied to runtime config by `InstanceConfigServiceProvider`.
- **ALLOWED_EMAIL_DOMAIN is a comma list**: `pshs.edu.ph,pshssystem.edu.ph` (subdomains of each accepted). The Firebase `hd` account-chooser hint is skipped when the list has multiple domains.

## Infrastructure

### Development
```
/Users/junlou/srmis-docker/                # Docker Compose root
  src/srmis/                               # Laravel app (this repo)
Services: php, mysql (port 3310), nginx (8100), soketi (6004), phpmyadmin (8101)
Dev URL:   http://localhost:8100   (everything — login auto-routes by email;
           /setup = wizard, /system = superadmin panel)
```
Run artisan in dev:
```bash
cd /Users/junlou/srmis-docker && docker compose exec php sh -c "cd /var/www/html/srmis && php artisan COMMAND"
```
Tenant ops: `php artisan tenants:migrate`, `tenants:seed --class=TenantDatabaseSeeder`, `tenants:run`.

### Production (AWS — mirrors CRCMIS stack)
ECS Fargate (`srmis-prod` / `srmis-prod-service`, ap-southeast-1), single container (nginx + php-fpm + cron + queue worker via supervisord), ECR `srmis/app`, RDS MySQL 8.0, ElastiCache Redis, S3, SSM Parameter Store (`/srmis/prod/*`), Secrets Manager (`srmis/google-drive-credentials`), Cloudflare standard SSL on ONE domain (no wildcard needed), GitHub Actions deploy on push to `main`. Entry point runs central `migrate` then `tenants:migrate` on boot.

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
