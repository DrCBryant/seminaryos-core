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

Master Assessment supports competency-based education where the student completes the section by demonstrating required outcomes at the required level.

For Kerygma’s competency-based model:

- some sections may include assignments along the way,
- but the official completion decision may rest on the Master Assessment.

In this foundation phase, Master Assessment tracks:

- the assessment definition,
- the student attempt record,
- the attempt status lifecycle.

This phase does **not** yet:

- calculate final section completion,
- update `CourseEnrollment` automatically,
- create `AcademicRecord` entries,
- change transcript, GPA, degree audit, or attendance logic.

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
