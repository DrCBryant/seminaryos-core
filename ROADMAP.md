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
- Academic Terms foundation implemented

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

## Immediate Next Task

Academic Terms foundation is already implemented. Current work is architecture reconciliation and refinement planning.

## Academic Terms Status

- [`AcademicTerm`](app/Models/AcademicTerm.php) exists as an institution-scoped academic scheduling model.
- The [`academic_terms`](database/migrations/2026_06_15_000000_create_academic_terms_table.php) migration already exists.
- Filament Academic Terms management already exists under [`app/Filament/Resources/AcademicTerms`](app/Filament/Resources/AcademicTerms).
- Existing academic operations already reference terms from [`CourseOffering`](app/Models/CourseOffering.php), [`CourseEnrollment`](app/Models/CourseEnrollment.php), [`TeachingAssignment`](app/Models/TeachingAssignment.php), [`AttendanceSession`](app/Models/AttendanceSession.php), [`AcademicRecord`](app/Models/AcademicRecord.php), and [`OfficialTranscriptLine`](app/Models/OfficialTranscriptLine.php).

## Known Academic Terms Follow-ups

- Normalize the canonical `term_type` vocabulary.
- Reduce duplicated term selector ordering and term label formatting across Filament resources.
- Clarify whether offering dates must remain inside term dates or may intentionally differ.
- Clarify whether overlapping active terms are allowed for intensives or modular calendars.
- Clarify catalog-to-term mapping without introducing direct coupling by default.
- Add seed/demo academic terms where appropriate for local and demo workflows.
