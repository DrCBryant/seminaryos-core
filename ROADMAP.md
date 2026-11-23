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

## Official Transcript Issuance Controls: Complete

- The registrar issue action is the only ordinary path to finalize a transcript. It locks and rechecks eligibility and commits issued metadata and snapshots together.

## Official Transcript Document Snapshots: Complete for New Issuances

- The issued view uses frozen identity, program, settings, term groups, and coursework. GPA inputs and summaries are captured. Legacy issuances remain identifiable without reconstructing their missing historical values.
- Publishing awards and aggregate GPA requires institutional policy. Their data contracts are documented.

## Official Transcript PDF Appearance and Export: Complete

- Authorized registrars can open the issued snapshot and save a searchable US Letter PDF through native browser print. The document includes continuation identification, page counts in supported browsers, term subtotals, and self-hosted open fonts.
- Trajan Pro deployment requires an appropriate license. Award selection and aggregate GPA publication remain separate policy work.

## Formal Degree Conferral: Registrar Workflow Implemented

- Eligible students can receive explicit, institution-authorized awards for their assigned program. Award history and eligibility evidence are immutable, and global Student status remains independently managed.
- Curriculum versions, institution-wide eligibility policy, delegated approval, award corrections, and transcript award selection policy remain future work.

## Portal Identity and Authorization Foundations: Complete

- Existing institutional accounts can link independently to Student and Faculty identities, including both in one institution. Administrators can manage links from the existing resources. Ownership and teaching-context gates depend on active tenant membership.
- Membership provisioning and additional delegated permissions remain separate work.

## Read-only Student Portal: Complete

- Linked students can use their existing account to view their overview, courses, finalized academic record, advisory degree progress, and conferred awards in the selected institution.
- Institution switching and student self-service workflows remain separate work.

## Faculty Portal Foundation: Complete

- Linked faculty can use their existing account to review assigned mentees and teaching rosters, competency evidence, section Master Assessment history, and recorded course progress and outcomes.
- Institution administrators assign and remove mentees from the existing Faculty edit page. Dual-identity users can open either portal from the same session.
- Instructor course views remain read-only. Any future delegated grade-entry UI must use existing grading and guarded completion workflows.

## Competency Mapping and Mentor Finalization: Complete

- Registrar administrators map section-bound Master Assessments to program competencies. An assigned Faculty Mentor can finalize a recorded passed attempt for its eligible mappings. The audit recognizes the permanent decision immediately, and the Registrar authorizes formal conferral.
- Section-independent assessment, new grading scales or mastery thresholds, reassessment limits, and a correction workflow remain institutional policy work.

## Faculty Mentor Master Assessment Entry: Complete

- Faculty Mentors review existing submitted attempts on mapped assessments and explicitly finalize Passed, Revision Needed, or Failed with feedback. A final Passed result records verified mastery in the same transaction. The Registrar continues to authorize formal conferral.
- The workflow uses recorded assessment guidance and existing result statuses. Reassessment, corrections, section-independent assessment, and new grading scales remain separate policy work.

## Outside the Completed Academic Terms Goal

Student self-service registration, late-registration approval, add/drop deadlines, optional demo data, and future vocabulary constraints require separately scoped work. Academic Terms refinement is no longer an active task.
