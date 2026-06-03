# Permissions and Tenancy

## Purpose

This document defines the operating model for permissions, institution membership, context switching, and Filament tenancy in SeminaryOS.

It is intended to clarify:

- who can access the admin system
- what data each role can see
- what records each role can edit
- how a single user can work across multiple institutions
- how tenant isolation should behave in Filament

This document is descriptive architecture guidance only. It does not define implementation code.

---

## Core Tenancy Principles

1. SeminaryOS uses a single shared database.
2. Institutions are the tenant boundary for business data.
3. Most domain records belong to exactly one institution.
4. Users may belong to one or many institutions.
5. A user's permissions are evaluated in the context of the currently selected institution.
6. Access should default to least privilege.
7. No user should see another institution's scoped records unless explicitly operating in that institution context.
8. Super Admin capability is exceptional and administrative, not the default model for ordinary institutional work.

---

## Role Definitions

The initial role model is based on the `institution_user.role` membership relationship described in [`plans/01-MULTI-INSTITUTION-DATABASE.md`](plans/01-MULTI-INSTITUTION-DATABASE.md).

Roles are interpreted as follows.

### 1. Super Admin

#### Purpose
Platform-level operator responsible for managing the SeminaryOS environment across institutions.

#### What Super Admin can see
- All institutions
- All institution memberships
- All institution-scoped records across all institutions, when intentionally operating in a selected institution context
- Platform-wide admin screens related to institution setup and cross-tenant governance

#### What Super Admin can edit
- Institution records
- Institution membership assignments
- Websites, website pages, programs, courses, catalogs, and catalog pages for any institution after switching into that institution
- Platform configuration areas that are not institution-owned

#### Boundaries
- Super Admin should not bypass tenant context casually in ordinary CRUD screens
- Even though this role can access all institutions, day-to-day record editing should still happen inside an explicit selected institution
- Super Admin is the only role that can create or deactivate institutions and manage cross-institution access

---

### 2. Institution Admin

#### Purpose
Primary administrator for one institution.

#### What Institution Admin can see
- The currently selected institution
- All institution-owned records for that institution
- Membership information for users who belong to that institution
- Administrative screens for website, academic content, and catalog operations within that institution

#### What Institution Admin can edit
- Institution profile fields that are intended to be locally manageable
- Institution website settings and website pages
- Programs and courses
- Catalogs and catalog pages
- Role assignments and memberships within that institution, except global platform authority

#### Boundaries
- Cannot manage other institutions unless they also have membership there
- Cannot perform platform-wide administration
- Cannot elevate themselves to platform-level authority

---

### 3. Faculty

#### Purpose
Academic contributor with responsibility for curriculum and possibly public academic content.

#### What Faculty can see
- The currently selected institution
- Published and draft academic content for that institution as needed for assigned work
- Programs, courses, and catalog records relevant to curriculum management
- Related generated catalog pages for review

#### What Faculty can edit
- Programs and courses, if the institution grants faculty academic editing authority
- Academic descriptions, learning outcomes, course content fields, and related curriculum mappings
- Draft catalog content derived from academic records where review workflow allows it

#### Boundaries
- Should not manage institution settings broadly
- Should not manage user memberships unless explicitly expanded later
- Should not edit unrelated website administration except where academic pages are intentionally shared with faculty workflows

---

### 4. Staff

#### Purpose
Operational user supporting institution administration, publishing, and content maintenance.

#### What Staff can see
- The currently selected institution
- Operational records needed for assigned work
- Website content, program content, course content, and catalog records when their job requires it

#### What Staff can edit
- Website pages and content operations when assigned
- Program, course, and catalog records when granted by institution policy
- Publishing and content review workflows that do not require platform authority

#### Boundaries
- Staff permissions should be narrower than Institution Admin by default
- Staff should not manage institution membership unless a future finer-grained permission system explicitly allows it
- Staff should not access other institutions without membership there

---

### 5. Student (future)

#### Purpose
Reserved role for future authenticated student-facing capabilities.

#### What Student can see
- Future student portal data for the currently selected institution
- Public or student-authorized academic information once student modules exist

#### What Student can edit
- Only their own future student-facing profile or workflow data, if later introduced

#### Boundaries
- Student is not part of the active admin operating model for the current phase
- Student should not be assumed to have Filament admin access
- Student-related permissions should remain dormant until student modules are explicitly introduced

---

## Visibility and Edit Matrix

| Role | Can See Institutions | Can See Institution-Scoped Data | Can Edit Institution Profile | Can Edit Website Content | Can Edit Programs/Courses | Can Edit Catalogs | Can Manage Memberships |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Super Admin | All institutions | All, within selected institution context | Yes | Yes | Yes | Yes | Yes |
| Institution Admin | Only member institutions | Yes, for current institution | Yes, for current institution | Yes | Yes | Yes | Yes, for current institution |
| Faculty | Only member institutions | Yes, for current institution and assigned workflows | No | Limited or none by default | Yes, where allowed | Limited review/edit as allowed | No |
| Staff | Only member institutions | Yes, for current institution and assigned workflows | No by default | Yes, where allowed | Limited or yes as assigned | Yes, where allowed | No by default |
| Student (future) | Only member institutions | Very limited future scope | No | No | No | No | No |

This matrix should be treated as the baseline policy model. Fine-grained permissions may later narrow or expand actions within a role.

---

## How Institution Switching Works

Institution switching is the act of choosing which tenant context the current user is actively operating inside.

### Rules

1. A user may switch only to institutions they belong to, unless they are Super Admin.
2. The selected institution becomes the current working tenant.
3. Institution-scoped queries in the admin should resolve against that selected institution.
4. Creating new institution-owned records should automatically associate them with the selected institution.
5. Switching institutions should immediately change visible navigation, record lists, dashboards, and CRUD access to reflect the new tenant context.

### Expected user flow

1. User signs in.
2. System determines the institutions available to that user.
3. If the user has one institution, that institution is selected automatically.
4. If the user has multiple institutions, the system should restore the last selected institution or prompt the user to choose one.
5. The chosen institution is stored as the active context for the session.
6. All institution-scoped admin operations use that active context until the user switches again.

### Why switching is necessary

Because one user can belong to multiple institutions, role and data visibility cannot be resolved correctly without an explicit current institution.

---

## How a User Can Belong to Multiple Institutions

SeminaryOS supports shared users across institutions through the `institution_user` membership relationship documented in [`plans/01-MULTI-INSTITUTION-DATABASE.md`](plans/01-MULTI-INSTITUTION-DATABASE.md).

### Membership model

- One user can have many institution memberships.
- One institution can have many users.
- Each membership stores the role for that institution.
- The same user may have different roles in different institutions.

### Example

- A consultant may be `admin` in Institution A.
- The same person may be `faculty` in Institution B.
- A platform operator may be `super_admin` and also hold ordinary institution memberships where needed.

### Important implication

Permissions are not attached only to the user globally. They are interpreted through the current institution membership.

That means the same user can experience different navigation, visibility, and edit rights depending on which institution is active.

---

## Filament Tenancy Operating Model

Filament should function as a tenant-aware admin interface, not as a flat global back office.

### Tenancy behavior

1. After login, Filament should resolve the user's available institutions.
2. Filament should expose a tenant switcher when the user has access to more than one institution.
3. The active tenant should control navigation, record queries, create actions, filters, and detail pages.
4. Institution-scoped resources should never show records from outside the active institution.
5. Institution selection should persist across requests in the user session.

### Resource behavior

For resources based on institution-owned models:

- list pages should show only active-tenant records
- create pages should assign the active tenant automatically
- edit pages should refuse records outside the active tenant
- relation managers should only surface related records inside the same tenant boundary

### Super Admin behavior in Filament

Super Admin should still use tenant switching for institution-owned resources.

Recommended behavior:

- global platform screens may remain visible outside tenant scope
- institution-owned resources should require an active tenant selection
- Super Admin may switch into any institution to work as though operating inside that tenant

This preserves clarity, reduces accidental cross-tenant mistakes, and keeps behavior consistent with the rest of the admin system.

### Institution Admin behavior in Filament

- sees only institutions they belong to
- cannot switch into institutions where no membership exists
- operates entirely inside institution-scoped navigation and CRUD

### Faculty and Staff behavior in Filament

- should see a reduced navigation set
- should only access resources necessary for assigned duties
- should remain constrained by both tenant scope and role scope

### Student behavior in Filament

- not part of the current Filament admin model
- future student-facing interfaces should likely be separate from the main operational admin panel

---

## Recommended Navigation Posture

Navigation should adapt to both role and tenant context.

### Super Admin navigation
- Institution management
- User and membership administration
- Tenant switcher
- Institution-scoped modules after selecting a tenant

### Institution Admin navigation
- Institution profile
- Website
- Website Pages
- Programs
- Courses
- Catalogs
- Catalog Pages
- Local membership management

### Faculty navigation
- Programs
- Courses
- Catalog review areas as permitted

### Staff navigation
- Website Pages
- Programs and Courses as permitted
- Catalog operations as permitted

### Student navigation
- None in current admin phase

---

## Security Expectations

The following security expectations must hold regardless of UI design.

1. Tenant filtering must not rely only on hidden form fields.
2. Record lookup must validate tenant ownership server-side.
3. Role checks must be evaluated for the active institution.
4. Switching institutions must invalidate assumptions from the previous tenant context.
5. A user must never gain access to another institution merely by changing URL parameters.

---

## Near-Term Policy Guidance

For the current phase of SeminaryOS, the simplest stable policy model is:

- Super Admin manages platform and can enter any tenant
- Institution Admin manages all institution-owned modules within their institution
- Faculty manages academic content where permitted
- Staff manages operational content where permitted
- Student remains future-only and outside current admin workflows

This is sufficient for the present institution, website, program, course, and catalog scope while leaving room for finer-grained permissions later.

---

## Future Expansion

Later phases may extend this document with:

- permission-level capabilities beyond broad roles
- section-level curriculum permissions
- publish vs edit separation
- reviewer and approver workflows
- student portal authorization
- faculty self-service profile and teaching assignments
- audit visibility policies

For now, role interpretation should remain simple, tenant-aware, and institution-centered.
