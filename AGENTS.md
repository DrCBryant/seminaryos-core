# SeminaryOS Agent Instructions

## Instruction Authority

- SeminaryOS architectural, registrar, deployment, safety, and business rules take precedence over generated Laravel Boost guidance.
- Existing repository conventions and documented architecture take precedence over generic framework recommendations.
- Laravel Boost guidance should be followed for version-specific Laravel, Filament, Livewire, PHPUnit, Pint, and MCP usage when it does not conflict with SeminaryOS rules.

## Agent Roles and Core Rule

- ChatGPT functions as SeminaryOS Chief Software Architect.
- The human/product architect and external architect prompts define implementation scope.
- Zoo Code is the implementation engineer.
- Zoo Code must inspect the existing codebase before making changes, must not redesign the architecture unless explicitly instructed, and must use the smallest safe modification.
- When scope is ambiguous, Zoo Code chooses the smallest safe implementation and reports assumptions.

## Project Context

SeminaryOS is a Laravel/Filament academic administration system for institutions, programs, courses, operational class sections, enrollments, completion review, competency-based outcomes, transcripts, faculty, applicants, students, and registrar workflows.

## Shared Hosting and Deployment

- The target deployment is ICDSoft shared hosting.
- Avoid Docker, Redis, Meilisearch, WebSockets, Supervisor, long-running queue workers, Node SSR, server-side PDF engines, binary dependencies, and external services unless explicitly approved.
- Prefer database-backed or file-backed Laravel features compatible with shared hosting.
- When background work is needed, prefer cron-compatible patterns.

## Development Principles

- Preserve the existing architecture and repository conventions.
- Reuse existing models, services, enums, actions, pages, tables, policies, and components whenever possible, in this order: existing domain service or action, existing model or relationship, existing Filament resource pattern, then a new abstraction only when clearly required.
- Check sibling files before creating or editing a file and follow their structure, approach, naming, and formatting.
- Use descriptive names, explicit readable code, and named routes with `route()` for generated links.
- Do not rename established files, classes, routes, or relationships unless required.
- Do not remove existing functionality or change application dependencies unless explicitly instructed.

## Safety Rules

- Never run destructive commands unless explicitly instructed.
- Do not run `php artisan migrate:fresh`, `php artisan db:wipe`, `git reset --hard`, `git clean`, `rm`, `del`, `rmdir`, or `docker system prune`.
- Protect bulk and destructive operations with validation, authorization, confirmation, transactions, and safe failure behavior where appropriate.

## Standard Workflow and Git

1. Inspect relevant files, documentation, status, and diff first.
2. Identify the smallest safe implementation path and preserve architectural boundaries.
3. Make the requested change.
4. Run relevant formatting and focused checks when executable files are affected.
5. Fix errors introduced by the change and review the complete diff against these rules.
6. Summarize files changed, implementation, checks/tests, remaining review, and warnings.

When the task is complete and checks pass, review `git status` and `git diff`, then stage, commit with an accurate concise message, and push when requested or within the architect's instructions. Do not claim success unless the requested implementation and checks actually completed.

## Documentation Stewardship

- Architectural decisions and stable reusable registrar rules must be preserved in repository documentation.
- Documentation may be created or updated when explicitly requested, required by the architect prompt, or needed to preserve a stable reusable architectural or registrar rule.
- Keep documentation concise and operational. Relevant locations include `AGENTS.md`, `ARCHITECTURE.md`, ADRs, the engineering handbook, and `docs/`.

## Completion Audit Visibility

- Expose completion audit data through existing enrollment and completion-review surfaces before introducing new audit pages or services.
- Audit displays must be read-only. They may show enrollment status, completion snapshot fields, reviewer details, override reason, and linked academic record summaries, but must not mutate completion state.
- Completion visibility changes must not alter `EnrollmentCompletionService` behavior unless the task explicitly concerns completion rules.

## Completion and Enrollment Rules

- Completion logic must always be guarded; never mark an enrollment complete without verifying eligibility.
- Reuse existing individual completion logic for bulk completion whenever possible.
- Bulk actions must filter or skip ineligible records before mutation. For durable academic operations, use transactions after eligible records are selected and fail safely if validation changes during execution.
- Report how many records were completed, skipped, or already complete.
- Preserve auditability for `completed_at`, `reviewed_by`, progress snapshots, transcript snapshots, academic records, and related notes.

## Durable Academic Records

- `AcademicRecord` creation, official transcript issuance, transcript snapshot lines, enrollment completion, and completion-review snapshots are durable academic actions.
- Do not rewrite, delete, or regenerate durable academic records unless explicitly instructed.
- Prefer append-only or snapshot-preserving behavior for official records.

## Progress and Completion Architecture

- `CourseOffering` currently functions as the operational Class Section model. Do not rename `CourseOffering`, `course_offerings`, routes, or relationships unless explicitly instructed.
- `CourseOffering.progress_basis` determines the evidence stream: `attendance`, `submissions`, `hybrid`, `manual`, or `master_assessment`.
- Use `SectionProgressEvaluator` as the source of truth for section progress evaluation.
- Do not duplicate section progress logic in Blade views, Filament pages, or table actions.

## Competency-Based Course Rules

- Do not assume attendance is always the basis for completion. Some courses track attendance, submitted work, or a master assessment.
- Completion logic must respect the course offering's model: attendance-based, assignment/work-based, competency/master-assessment-based, or manual registrar review.

## Laravel and PHP Context

The installed framework context is:

- PHP 8.5
- `laravel/framework` 13
- `filament/filament` 5
- `livewire/livewire` 4
- `laravel/boost` 2
- `laravel/mcp` 0.x
- `laravel/prompts` 0.x
- `laravel/pail` 1
- `laravel/pint` 1
- `phpunit/phpunit` 12

Follow Laravel conventions, keep relationships clear, avoid raw SQL unless necessary, prefer existing services for business logic, and keep complex business logic out of Blade views. Use database transactions for multi-record state changes.

Use PHP 8 constructor property promotion, explicit parameter and return types, curly braces for control structures, TitleCase enum keys, PHPDoc blocks rather than ordinary inline comments, and array-shape PHPDoc where useful. Do not add factories or seeders automatically for every model; add them when useful, consistent with repository patterns, and within task scope.

Use `php artisan make:` commands for new Laravel files and pass `--no-interaction` with appropriate options. Prefer existing Artisan commands over custom Tinker code. If Tinker is necessary, use single quotes around the PHP expression to prevent shell expansion. Use Boost tools when available and appropriate, but repository inspection and standard Artisan commands remain valid.

## Laravel Boost and MCP

- Activate the relevant project skill when working in its domain.
- Use Boost MCP tools when available and appropriate. Use `database-schema` before schema or model work, `database-query` for read-only database inspection, `browser-logs` for recent browser errors, and `get-absolute-url` before sharing an application URL.
- Use `search-docs` before framework-specific implementation, scoped to the relevant installed packages when known. Use broad topic queries and version-specific results.
- Keep generated MCP configuration and skills available unless they are clearly incomplete or unsafe.

## Filament

- Follow existing resource structure and nearby conventions. Prefer built-in Filament patterns and static `make()` methods.
- Form fields use `Filament\Forms\Components\`; infolist entries use `Filament\Infolists\Components\`; layout components use `Filament\Schemas\Components\`; schema utilities use `Filament\Schemas\Components\Utilities\`; table columns use `Filament\Tables\Columns\`; table filters use `Filament\Tables\Filters\`; actions use `Filament\Actions\`; icons use `Filament\Support\Icons\Heroicon`.
- Use `Get` and `Set` from the schema utilities for reactive conditional fields and state updates. Use `Repeater::make(...)->relationship()->schema(...)` for inline HasMany management when appropriate.
- Use `state()` for computed table values, relationship-aware `Select` fields for BelongsTo fields, and `Filter` or `SelectFilter` for table filtering.
- Use confirmation modals for dangerous or bulk actions. Do not assume public file visibility; specify `->visibility('public')` when public access is required. Do not use `->dehydrated(false)` for fields that must be saved.
- Preserve correct Filament 5 property types when overriding resources, pages, and widgets, including union types for navigation properties and non-static `$view` properties.

## Testing

- PHPUnit is the project's testing framework. Write PHPUnit test classes and do not convert tests to Pest or copy Pest-specific examples into project guidance.
- Application behavior changes require relevant automated tests. Documentation-only changes do not require application tests unless executable or generated behavior is affected.
- Run focused tests relevant to the change. Run the full suite when instructed, when risk warrants it, or when the suite is reasonably small; do not require permission before doing so.
- Cover relevant happy paths, failure paths, and edge cases. Use existing factories and repository testing conventions.
- Do not remove tests without explicit approval.

## Formatting and Verification

- For PHP changes, run the platform-appropriate equivalent of `vendor/bin/pint --dirty --format agent`; on Windows, `php .\vendor\bin\pint --dirty --format agent` is acceptable.
- Do not run Pint for Markdown-only changes unless PHP files also changed.
- For most feature work, run the applicable commands one at a time:

```text
php .\vendor\bin\pint --dirty --format agent
php artisan optimize:clear
php artisan route:list --name=filament
php artisan about
```

- Run `php artisan test` when application behavior changes or when tests are otherwise relevant. Do not claim tests passed unless they ran successfully.
- If a Vite manifest error occurs, run `npm run build`, `npm run dev`, or `composer run dev` as appropriate.

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
