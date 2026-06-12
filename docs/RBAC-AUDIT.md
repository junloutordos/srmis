# SRMIS RBAC Audit — Extraction from CRCMIS (June 2026)

Scope: all roles, permissions, policies and gates carried over from the
CRCMIS/BugSayMis monolith into SRMIS, audited during extraction. Every
permission string referenced by route middleware, controllers, or the sidebar
is registered in `TenantPermissionsSeeder` (+ `OrgStructurePermissionsSeeder`),
and every role grant lives in `TenantRolePermissionSeeder`. Each tenant
(campus) schema gets its own copy — no cross-tenant role or permission rows
exist anywhere.

## Architecture decisions

- **Tenant isolation** — schema-per-tenant via stancl/tenancy v3
  (`srmis_<campus>` schemas on one MySQL instance). Users, roles, permissions,
  sessions and all module data live inside the tenant schema; there is no
  shared user table, so a credential or role in one campus cannot grant
  anything in another. Verified: a `crc` session cookie presented to
  `oed.<domain>` is rejected (sessions are read from the oed schema).
- **Central plane separation** — system superadmins live in `central_users`
  with a dedicated `central` guard; they have no identity inside tenant
  schemas. The tenant `Gate::before` callback now guards with
  `instanceof App\Models\User`, so a central account can never satisfy a
  tenant permission check (previously the closure was typed to `User` and
  would have fataled — now it falls through to a deny).
- **Module activation** — `EnsureModuleEnabled` runs on the entire tenant
  route group and maps request paths to the seven modules; a module switched
  off for a campus 403s every route in it (verified live).
- **Setup wizard lockout** — `/setup/*` routes are wrapped by
  `EnsureNotInstalled` and abort 404 permanently once the instance is marked
  installed, so the wizard cannot be re-run to register a rogue superadmin.

## Gaps found in the monolith and closed in SRMIS

| # | Finding (monolith behavior) | Fix in SRMIS |
|---|---|---|
| 1 | `work-requests.gsu.approve/decline(.submit)` email links had **no `signed` middleware** while every analogous DC/FAD/OCD link was signed — a guessable URL could approve work requests. | All three GSU routes now require a valid signature. |
| 2 | OCD approval dashboards/actions for vehicle, facility and messengerial requests had **no permission middleware** (only `auth`). | Gated behind new `vehicles.ocd-approve` / `facilities.ocd-approve` permissions, granted to the OCD role. |
| 3 | CSM Feedback Center pages (`/csm/dashboard`, `/csm/list`, `/csm/export`) had **no permission middleware**. | Gated behind `permission:it.requests.manage`. |
| 4 | ICT equipment and ICT PMS routes had **no permission middleware** at all. | Reads require `it.equipment.view`; writes require `it.equipment.manage`. |
| 5 | Org Structure unit CRUD routes carried a comment "require org.units.* permissions" but **no middleware was attached**. | Every org route now enforces the documented permission (`org.view`, `org.units.create/update/delete`, `org.assign.manage`, `org.heads.manage`, `org.versions.view/manage`, `org.export`, `org.reports`). |
| 6 | User create/update/delete/activate and role/division mutations were gated only by `users.view` — any user-list viewer could mutate accounts. | Mutations now require `users.manage` (users) or `roles.assign` (roles/divisions); `users.view` is read-only. |
| 7 | Reports index was gated by `users.view` (unrelated permission). | New `reports.view` permission; audit logs additionally require `roles.assign`. |
| 8 | ITJR `update-priority` had no permission check. | Requires `it.requests.manage`. |
| 9 | Driver list/assign API had no permission check. | Requires `vehicles.manage`. |
| 10 | Assets CRUD relied on group-level auth only. | Wrapped in `facilities.manage`. |
| 11 | `AuditLogger::log()` threw when the audit table was missing, and audit rows were attempted outside tenant context. | Skips silently outside an initialized tenant; failures are logged, never fatal. Audit logs are tenant-scoped by construction. |
| 12 | `composer audit`: phpoffice/phpspreadsheet **critical** (CVE-2026-45034) + 3 low symfony/yaml CVEs. | Dependencies updated; `composer audit` is clean. |
| 13 | Email-domain check hardcoded `@crc.pshs.edu.ph` in three places. | Centralised in `ALLOWED_EMAIL_DOMAIN` config (`EnsureAllowedEmailDomain` middleware + `GoogleAuthController::emailDomainAllowed()`); accepts the domain and its subdomains, e.g. `pshs.edu.ph` covers all campuses. |

## Carried-over protections (verified still in force)

- `Gate::before` dynamic permission resolution with SuperAdmin
  (Administrator role) bypass — unchanged semantics, per tenant.
- Approval Inbox re-validates per-record authority (`authoriseApprove`)
  — division chiefs can only act on their own division's records — and
  re-checks pending state (409 on double-action). Gate pass and leave
  application types were removed with the HR module.
- Signed email-link approvals for DC/FAD/OCD across all request modules.
- Login throttling (5/min), password policy (12+ chars via wizard, 10+
  letters/numbers/symbols/uncompromised for tenant users), audit logging of
  login/logout and all model events.
- Digital signature PIN endpoints require the authenticated session;
  signature verification pages are public by design (QR landing).

## Verification performed (dev stack, 2026-06-12)

- 22 module pages render 200 for a campus Administrator on `crc`.
- Unauthenticated requests to every sensitive path redirect to login
  (tenant) or central login (central admin).
- A `Staff` user gets 403 on `/users`, `/admin/rbac/roles`, `/mis/dashboard`,
  `/reports`, `/users-roles`, `/data-management/offices`, `/inbox`, while
  retaining access to request filing pages — matching the seeded matrix.
- Tenant routes 404 on the central domain; central routes are domain-bound
  and never shadow tenant subdomains.
- Module toggle (`chat` off for crc) returns 403 on `/chat` and
  `/api/chat/*`, restored on re-enable.


## Addendum — single-domain conversion (2026-06-12)

The subdomain scheme was replaced by single-domain tenancy (campus detected
from the login email). Isolation re-verified under the new model:

- **Tenant binding lives server-side**: the campus is stored in the session
  (`tenant_id`) at login; sessions are central and the binding cannot be
  altered from the client. Cookies are Laravel-encrypted.
- **Signed email links** carry `?tenant=<slug>` inside the signed portion
  (TenantAwareUrlGenerator). ResolveTenant only honors the parameter when the
  signature validates; a tampered campus value → 403 before any DB query
  (ValidateSignature is prioritized ahead of route-model binding).
- **Middleware order enforced** via explicit `$middleware->priority([...])`:
  StartSession → ResolveTenant → Authenticate → ValidateSignature →
  SubstituteBindings. This guarantees the user lookup and route binding always
  run inside the right tenant schema.
- **Central users** (`CentralUser`, guard `central`) are shared to Inertia as
  guests — they can never satisfy tenant permission checks or appear as a
  campus user.
- Re-verified live: CRC and OED sessions in parallel on one domain resolve to
  their own schemas; `@pshssystem.edu.ph` → OED special-case works;
  unrecognised domains get a 422 at login; the Staff 403 matrix and per-tenant
  module toggles behave identically to the subdomain build.
