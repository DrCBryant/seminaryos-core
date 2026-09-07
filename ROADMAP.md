# SeminaryOS Roadmap Checkpoint

## Current Completed Foundation

- Laravel 13
- MySQL
- Filament 5.6
- Multi-institution architecture
- Institution CRUD
- Website CRUD
- Program CRUD
- Course CRUD
- Catalog CRUD
- Applicant CRUD
- Student CRUD
- Academic Terms foundation and refinement complete

## Next Build Phase: Academic Operations

1. Course Enrollments
2. Faculty
3. Applicant-to-Student Conversion
4. Academic Records
5. Transcript Engine
6. Catalog Publishing Engine
7. Public Application Form
8. Student Portal
9. Faculty Portal

## Academic Terms: Complete

- Existing institution-scoped model, relationships, migration, and Filament management preserved.
- Live labels, selector ordering, term type, and status vocabulary centralized.
- Registrar-managed status, overlapping terms, and simultaneous active terms supported.
- Section date exceptions remain advisory and non-blocking.
- Optional registration windows evaluated through reusable model methods with inclusive boundaries and one-sided windows.
- Registration dates and current advisory state visible in existing term/enrollment surfaces; administrative enrollment remains possible outside the window.
- Form date consistency and focused PHPUnit coverage completed; architecture recorded in `ARCHITECTURE.md`.
- Durable official transcript snapshots and independent Catalog effective dates preserved.

## Outside the Completed Academic Terms Goal

Student self-service registration, late-registration approval, add/drop deadlines, portal work, optional demo data, and future vocabulary constraints require separately scoped work. Academic Terms refinement is no longer an active task.
