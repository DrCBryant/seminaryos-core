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

## Course Enrollments: Complete

- Existing CourseEnrollment, guarded completion, AcademicRecord, CourseOffering, and transcript architectures preserved.
- `Course.scheduling_basis` distinguishes default term-bound coursework from completion-date grouped courses such as Kairos.
- Enrollment AcademicTerm context is nullable for valid non-term coursework; direct/manual and CourseOffering-backed workflows remain supported.
- Completion-date reporting resolves only one inclusive standard reporting term (`fall`, `spring`, `summer`, or `winter`) from the durable completion date. Specialized terms are ignored; ambiguity and no-match outcomes require registrar review.
- Official transcript issuance preserves durable snapshot labels and fails safely before issuance when reporting context is unresolved.

## Faculty: Complete

- Existing institution-scoped Faculty identity remains independent from User authentication.
- TeachingAssignment remains the instructional assignment record, with CourseOffering as authoritative section context.
- Faculty and assignment status/role vocabularies are centralized; inactive faculty history remains inspectable.
- Completion-date coursework may use direct assignments without an artificial AcademicTerm.
- Hard-delete actions that could erase assignment history were removed from Faculty and TeachingAssignment administration.

## Applicant-to-Student Conversion: Complete

- Accepted applicants convert transactionally into one linked Student while the Applicant admissions record is preserved.
- Conversion is idempotent and duplicate-safe, with same-institution email collisions surfaced for registrar review.
- Institution, program, identity, contact, notes, student number, active status, and conversion date are preserved without academic side effects.
- Existing registrar Applicant management exposes conversion status and the guarded conversion action.

## Academic Records: Complete

- AcademicRecord remains the durable completed-course result created through guarded enrollment completion.
- Course, grade, credit, completion, GPA, and provenance snapshots are preserved independently from mutable upstream definitions.
- Term-bound records retain direct AcademicTerm context; completion-date records preserve nullable term context and resolve reporting context without mutation.
- Registrar edit surfaces protect durable outcome fields while retaining notes management, and unsafe hard-delete actions are not exposed.
- Official transcript lines remain separate issuance snapshots and are not rewritten by later academic-record changes.

## Transcript Engine: Complete

- Existing OfficialTranscript and OfficialTranscriptLine layers remain intact, with issuance as the transactional snapshot boundary.
- Issued rendering uses durable transcript lines; preview remains current-state and advisory.
- Direct term context, completion-date reporting resolution, deterministic ordering, GPA snapshots, and unresolved-term issuance safeguards are preserved.
- Issued transcript history is protected from routine force deletion and mutable upstream changes.

## Outside the Completed Academic Terms Goal

Student self-service registration, late-registration approval, add/drop deadlines, portal work, optional demo data, and future vocabulary constraints require separately scoped work. Academic Terms refinement is no longer an active task.
