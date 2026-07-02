# SeminaryOS ICDSoft Hosting Guide

## Purpose

This document defines the technical rules SeminaryOS should follow to deploy cleanly on **ICDSoft shared hosting**. It is intentionally written for ICDSoft as the target environment, not generic shared hosting.

## ICDSoft Target Environment Assumptions

SeminaryOS should assume the following ICDSoft-compatible baseline unless an account-specific limitation is confirmed:

- PHP 8.4.1 or higher
- MySQL
- Apache/LiteSpeed-style hosting behavior
- [`.htaccess`](../public/.htaccess) support
- SSH access
- Composer available at `/usr/local/bin/composer`
- Cron jobs available
- Public web root can be configured or adapted
- Writable [`storage/`](../storage/) and [`bootstrap/cache/`](../bootstrap/cache/) directories

## SeminaryOS Production Recommendations for ICDSoft

### PHP and Database

- Use **PHP 8.4.1** as the production baseline unless the ICDSoft account explicitly confirms a newer compatible PHP version for the deployed Laravel and package stack.
- The current SeminaryOS dependency stack includes Symfony 8.1 packages that require **PHP 8.4.1 or higher**, so PHP 8.3 is no longer a valid deployment or CI baseline.
- Use **MySQL** in production.

### Sessions, Cache, and Queue

- Use **database-backed sessions**.
- Use **database-backed cache** unless file cache demonstrates better performance on the specific ICDSoft account.
- Use **database-backed queues**, but do **not** require long-running queue workers.

### Scheduler and Background Work

- Use cron to run Laravel scheduler through [`artisan`](../artisan).
- Design background jobs so they can run through **database queue + cron**, not persistent worker infrastructure.

### Platform Assumptions to Avoid

SeminaryOS should avoid depending on hosting features that are usually unsuitable for ICDSoft shared hosting:

- Redis
- Meilisearch
- WebSockets
- Supervisor
- Docker
- Node SSR
- VPS-only assumptions
- Required system binary dependencies unless clearly optional

## Laravel Deployment Notes for ICDSoft

### Preferred Layout

Preferred deployment layout:

- Laravel app lives **outside** the public web root when possible
- Domain document root points directly to Laravel [`public/`](../public/)

This is the cleanest and safest ICDSoft deployment model.

### Alternative Layout If Document Root Cannot Point to `public/`

If ICDSoft account/domain constraints prevent document root mapping directly to [`public/`](../public/), SeminaryOS should document and use a careful fallback:

- use a controlled [`public_html/index.php`](../public/index.php) bridge approach, or
- use a symlink approach **only if** ICDSoft account behavior confirms it works safely

This fallback must be handled carefully to avoid exposing application files outside the intended web surface.

### Environment and Security Rules

- [`.env`](../.env.example) must **not** be committed
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` must be generated on deployment

### Deployment Commands

Standard ICDSoft-friendly deployment flow should include:

- `/usr/local/bin/composer install --no-dev --optimize-autoloader`
- `php artisan migrate --force`
- `php artisan config:cache`
- `php artisan route:cache` only if all routes support caching
- `php artisan view:cache`

### Course Offering Section-Code Hardening Preflight

Before deploying the course offering section-code hardening migration to production data, check for duplicate course offerings within the same institution, course, and academic term where `section_code` is blank or `NULL`. Those values normalize to `MAIN`, so duplicates can conflict with the course offering section uniqueness rule. If there is no existing course offering data, this preflight is not blocking.

### Writable Directories

Ensure these directories are writable by the hosting environment:

- [`storage/`](../storage/)
- [`bootstrap/cache/`](../bootstrap/cache/)

### Scheduler / Cron

Configure cron to run Laravel scheduler when scheduled jobs exist, for example:

- `php artisan schedule:run`

This should be executed via cron on the ICDSoft account rather than assuming a resident scheduler process.

## ICDSoft Feature-Specific Rules

### Transcript Preview

- Transcript preview is safe on ICDSoft because it is **database-read only**.
- The current transcript preview implementation should remain lightweight and server-rendered.

### Transcript PDF Generation

- Transcript PDF generation must use a **shared-hosting-safe** PDF method.
- Avoid solutions that require nonstandard server binaries, heavy headless browser dependencies, or large memory overhead unless explicitly validated on ICDSoft.

### Email

- Email should use **SMTP** or provider APIs.
- Do not assume local mail transport is reliable or preferred on ICDSoft.

### File Uploads

- File uploads must respect ICDSoft account storage limits.
- Upload validation, file size controls, and retention policy should be designed with shared hosting disk limits in mind.

### Background Jobs

- Background jobs must be runnable through **database queue + cron**.
- Do not require persistent workers.

### Imports and Exports

- Imports and exports should be **chunked** and **memory-conscious**.
- Avoid large single-request processing patterns that may exceed shared hosting memory or execution time limits.

## Operational Rules SeminaryOS Should Follow on ICDSoft

### Configuration Strategy

- Prefer Laravel features that operate correctly with MySQL, cron, filesystem writes, and standard PHP execution.
- Keep package selection conservative when features must run on shared hosting.

### Performance Strategy

- Favor cached configuration, cached views, and route caching where compatible.
- Prefer synchronous admin experiences unless queueing is clearly needed.
- Keep admin tools efficient in query volume and memory usage.

### Reliability Strategy

- All scheduled and queued features should degrade gracefully if cron timing is delayed.
- Long-running or batch operations should be restartable and safe to resume.

## Do Not Build Without Rechecking ICDSoft Compatibility

Before implementing or expanding any of the items below, recheck ICDSoft compatibility directly against the target account and feature design:

- PDF generation
- bulk imports
- transcript batch exports
- email campaigns
- file storage
- backups
- search
- queues
- real-time notifications
- LMS/media features

## SeminaryOS-Specific Implications

### Safe Today

The following current SeminaryOS capabilities are generally ICDSoft-friendly if kept lightweight:

- Filament admin resources such as [`ApplicantResource`](../app/Filament/Resources/Applicants/ApplicantResource.php), [`StudentResource`](../app/Filament/Resources/Students/StudentResource.php), and [`AcademicRecordResource`](../app/Filament/Resources/AcademicRecords/AcademicRecordResource.php)
- database-driven workflows such as applicant conversion, enrollment completion, teaching assignments, and academic records
- transcript preview modal behavior reading from academic records

### Needs Future ICDSoft Review

The following SeminaryOS areas should be reviewed again before expansion:

- transcript PDF generation from the transcript preview workflow
- bulk data import/export features
- email-heavy workflows for admissions, registrar, or notifications
- large file/media handling for catalogs, course assets, or LMS-like features
- queue-heavy automation beyond cron + database queue limits
- backup strategy and retention handling
- any search layer beyond MySQL-backed querying
- any real-time or live-update feature expectations

## Recommended Default Production Position for SeminaryOS on ICDSoft

Use the following default stance unless a specific ICDSoft account proves more capable:

- PHP 8.4.1
- MySQL
- Laravel app outside web root when possible
- document root mapped to [`public/`](../public/)
- database sessions
- database cache by default
- database queue with cron-driven execution
- SMTP/API email delivery
- no Redis, no persistent workers, no WebSockets, no SSR, no Docker assumptions

This keeps SeminaryOS aligned with ICDSoft shared hosting realities while preserving a clean Laravel deployment model.
