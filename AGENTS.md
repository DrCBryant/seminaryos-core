# SeminaryOS Agent Instructions

## Core Rule

Before making changes, inspect the existing codebase. Do not redesign the architecture. Implement only the requested change using the smallest safe modification.

## Agent Roles

- The human/product architect and external architect prompts define implementation scope.
- Zoo Code is the implementation engineer.
- Zoo Code should not redesign architecture unless explicitly instructed.
- When scope is ambiguous, Zoo Code should choose the smallest safe implementation and report assumptions.

## Project Context

SeminaryOS is a Laravel/Filament academic administration system for managing institutions, programs, courses, course offerings, enrollments, completion review, competency-based outcomes, transcripts, faculty, applicants, students, and registrar workflows.

## Shared Hosting Constraints

- Target deployment is ICDSoft shared hosting.
- Avoid Docker, Redis, Meilisearch, WebSockets, Supervisor, long-running queue workers, Node SSR, server-side PDF engines, binary dependencies, and external services unless explicitly approved.
- Prefer database-backed or file-backed Laravel features that remain compatible with shared hosting.
- When background work is needed, prefer cron-compatible patterns.

## Development Principles

- Preserve the existing architecture.
- Reuse existing models, services, enums, actions, pages, tables, and policies whenever possible.
- Do not create new abstractions unless the current feature clearly requires them.
- Keep Filament patterns consistent with the existing resources.
- Prefer explicit, readable code over clever code.
- Do not rename established files, classes, routes, or relationships unless required.
- Do not remove existing functionality unless explicitly instructed.

## Safety Rules

Never run destructive commands unless explicitly instructed.

Do not run:

```bash
php artisan migrate:fresh
php artisan db:wipe
git reset --hard
git clean
rm
del
rmdir
docker system prune
```

## Standard Workflow

For every coding task:

1. Inspect relevant files first.
2. Identify the smallest safe implementation path.
3. Make the requested change.
4. Run formatting:

```bash
vendor/bin/pint --dirty
```

5. Run relevant checks or tests when available.
6. Fix any errors introduced by the change.
7. Summarize:
   - Files changed
   - What was implemented
   - Checks/tests run
   - Anything that still needs review

## Git Workflow

When the task is complete and checks pass:

```bash
git status
git diff
git add .
git commit -m "Clear, concise commit message"
git push
```

Use a commit message that accurately describes the feature or fix.

## Laravel Rules

- Follow Laravel conventions.
- Keep model relationships clear and consistently named.
- Avoid raw SQL unless necessary.
- Prefer existing services or actions for business logic.
- Keep complex business logic out of Blade views.
- Use database transactions for multi-record state changes.
- Protect bulk operations with validation and confirmation where appropriate.

## Filament Rules

- Match the existing resource structure.
- Keep page actions, table actions, forms, and infolists consistent with nearby resources.
- Use confirmation modals for dangerous or bulk actions.
- Prefer built-in Filament patterns over custom UI.

## Completion and Enrollment Rules

- Completion logic must always be guarded.
- Never mark enrollments complete without verifying eligibility.
- Reuse existing individual completion logic for bulk completion whenever possible.
- Bulk actions should filter or skip ineligible records before mutation. For durable academic operations, once eligible records are selected, use transactions and fail safely if validation changes during execution.
- Report how many records were completed, skipped, or were already complete.
- Preserve auditability for completed_at, reviewed_by, progress snapshot fields, transcript snapshots, academic records, and related notes.

## Durable Academic Records

- AcademicRecord creation, official transcript issuance, transcript snapshot lines, enrollment completion, and completion review snapshots are durable academic actions.
- Do not rewrite, delete, or regenerate durable academic records unless explicitly instructed.
- Prefer append-only or snapshot-preserving behavior for official records.

## Progress and Completion Architecture

- CourseOffering currently functions as the operational Class Section model.
- Do not rename CourseOffering, course_offerings, routes, or relationships unless explicitly instructed.
- CourseOffering progress_basis determines the evidence stream:
  - attendance
  - submissions
  - hybrid
  - manual
  - master_assessment
- Use SectionProgressEvaluator as the source of truth for section progress evaluation.
- Do not duplicate section progress logic in Blade views, Filament pages, or table actions.

## Competency-Based Course Rules

Some courses track attendance. Others track submitted work or a master assessment.

Do not assume attendance is always the basis for completion.

Completion logic must respect the course offering's completion model:
- Attendance-based
- Assignment/work-based
- Competency/master-assessment-based
- Manual registrar review

## Verification Commands

For most feature work, run these one at a time:

```bash
vendor/bin/pint --dirty
php artisan optimize:clear
php artisan route:list --name=filament
php artisan about
```

When tests exist or are relevant, also run:

```bash
php artisan test
```

Do not claim tests passed unless they were actually run.

## Documentation Rules

- When a feature introduces a permanent architectural or registrar business rule, update or recommend updates to:
  - AGENTS.md
  - PROJECT.md
  - ARCHITECTURE.md
  - ADRs
  - engineering handbook
  - relevant docs under docs/
- Keep documentation concise and operational.

## Output Format

At the end of every task, provide:

```text
Implemented:
- ...

Changed files:
- ...

Checks run:
- ...

Commit:
- ...

Notes:
- ...

Warnings:
- ...
```

Do not claim success unless the requested implementation and checks actually completed.
