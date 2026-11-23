# Class Section Progress Architecture

## Current model terminology

In the current SeminaryOS codebase, [`CourseOffering`](app/Models/CourseOffering.php) is the operational **Class Section** model.

- The database table remains [`course_offerings`](database/migrations/2026_07_02_034000_create_course_offerings_table.php).
- No rename is being introduced at this stage.
- Throughout this phase, `CourseOffering` should be treated as the section-level operational record for scheduling, enrollment, attendance, and future progress evaluation.

## Core principle

Section completion is the goal.

Attendance, submissions, manual approval, and master assessment attempts are **evidence streams** that may be used to determine whether a student has satisfied the section.

Attendance is therefore **not** the universal or primary mechanism for determining progress across all sections.

## Progress basis / completion method

Each section now supports a configurable progress basis through the `progress_basis` field on `CourseOffering`.

Supported values:

- `attendance`
- `submissions`
- `hybrid`
- `manual`
- `master_assessment`

### Meaning of each option

#### attendance

Used for live or classroom-oriented sections where attendance is the primary evidence that the student completed the section.

#### submissions

Used for asynchronous, assignment-based, or work-product-based sections where submitted work is the primary completion evidence.

#### hybrid

Used for sections requiring both attendance and submitted work.

#### manual

Used for independent studies, mentoring, practicums, or other instructor-approved completion flows where progress is determined manually.

#### master_assessment

Used for competency-based sections where completion rests on a major assessment, capstone demonstration, or competency verification rather than ordinary attendance alone.

## Attendance treatment

Attendance remains part of the platform, but it is now architecturally treated as **optional evidence**.

- Attendance should govern completion only when `progress_basis` is `attendance` or `hybrid`.
- Attendance data may still be collected for other section types for informational or operational reasons.
- Attendance features are not removed or blocked in this phase.
- Existing attendance sessions and attendance records remain intact.

## Future evidence streams

### Assignment submissions

Assignment submissions will later support:

- `submissions`
- `hybrid`

This phase does **not** yet implement submissions, LMS workflows, or student submission portals.

### Manual approval

Manual approval will later support:

- independent studies
- mentoring
- practicums
- instructor-approved completion paths

This phase does **not** yet implement full section completion approval workflows.

### Master Assessment

A section configured with the `master_assessment` progress basis uses `SectionProgressEvaluator` to evaluate the student's attempts against the active assessments for that section. Passed attempts can support a guarded enrollment completion decision. The evaluator reports progress, and `EnrollmentCompletionService` records the course outcome when completion is authorized and validated.

A Master Assessment belongs to a section. An attempt can be stored without an enrollment because its enrollment foreign key is nullable. That still leaves the attempt tied to a section and does not establish a program-level competency assessment workflow.

A course or section may provide learning evidence for several program competencies. The course outcome, artifacts, and section assessment results can support a later mastery decision. Competency attainment requires a verified result from the Master Assessment applicable to the program requirement. The degree audit leaves explicit competency requirements unevaluated until that verification exists. Existing manual evidence and substitutions continue to apply to eligible requirements of other types.

Faculty Mentors require an explicit assignment to the student to assess competency mastery. Course Instructors require an applicable teaching assignment to enter traditional course grades. Both checks use the active institution and may apply to one faculty member, including someone who also holds a Student identity. Current faculty teaching gates authorize reading the assigned context. Faculty assessment entry must reuse the existing grading and completion workflows once institutional authority and review rules are defined.

See [ADR 0003](adr/0003-distinguish-course-achievement-from-competency-attainment.md).

## Scope of this foundation

This implementation establishes:

- configurable section progress basis on `CourseOffering`,
- documentation clarifying attendance as optional evidence,
- a database and Filament foundation for Master Assessments,
- a student attempt tracking model for competency demonstration workflows.

It intentionally avoids introducing:

- LMS features,
- background jobs,
- external services,
- binary dependencies,
- transcript or registrar-side completion automation.
