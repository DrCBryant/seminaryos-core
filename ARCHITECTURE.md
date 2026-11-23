# SeminaryOS Architecture Notes

## Portal Identity and Institutional Authorization

- `User` is the shared Laravel authentication account. `Student` and `Faculty` are independent academic identities. An institution membership can link explicitly to one of each at the same time. One account can hold different links and roles in other institutions. A link never creates an academic record or matches by email.
- The selected `current_institution_id` must resolve to an active institution with an active membership and an active account. Authenticated tenant queries fail closed when any element is missing. Unauthenticated jobs, imports, and console operations retain the existing query behavior. Web entry points must apply authorization before exposing academic data.
- Tenant context is necessary for portal access. The application's named gates separately check student ownership, faculty ownership, and active teaching assignment. Faculty assignment checks grant access to the assigned teaching context only. Portal experiences use the shared session and choose the gates that apply to their academic identity.
- The admin panel and registrar print page require administrative membership in the selected institution. The `admin` and `super_admin` roles can manage identity links when the record can be edited. Staff retain registrar panel access and cannot manage links. The existing Student and Faculty edit actions link, replace, or clear an active institutional account.
- Composite foreign keys bind identity and membership to the same institution. Removing an account or membership removes its links. Suspending an account or membership disables access while preserving the links. Neither operation deletes the academic identities or their history. Separate historical references can still block hard deletion of an authorizing account.
- See [ADR 0002](docs/adr/0002-link-academic-identities-to-institution-memberships.md) and the [permissions guide](docs/08-PERMISSIONS-AND-TENANCY.md) for the authorization contract and deferred policy decisions.

### Read-only Student Portal

- The Student Portal at `/student` uses the shared Laravel session. It requires an active account, an active membership in the selected active institution, and an explicitly linked Student identity. It checks `view-own-student-identity` on every render and loads data through the linked Student and tenant-scoped queries. The URL selects a view without accepting a student or record ID.
- The portal displays current enrollments and finalized academic history. Course outcomes come from the linked academic record, and the reporting term follows the existing registrar resolver. Degree Progress is advisory and shows student-facing audit results. Awards display the program details and date captured at conferral.
- Eligible users can open the Faculty Portal or Registrar workspace from the same session. A user-facing institution switcher remains future work.

### Faculty Assessment Authority

- Faculty Mentor and Course Instructor describe assigned academic responsibilities. A faculty member may hold both assignments at the same time, including while holding a Student identity through the same account.
- Faculty Mentors have final academic authority over competencies for their assigned students. An assigned mentor can finalize a successful Master Assessment for its applicable mapped competencies. Course Instructors assess enrolled students in their assigned traditional courses through existing grading and guarded completion workflows.
- Authorize each assessment action in the active institutional context using the applicable student mentorship or course teaching assignment. Each assignment grants authority only within its assigned scope.
- The mentor’s finalized mastery decision immediately satisfies the mapped competencies in the degree audit. The Registrar verifies overall completion and authorizes formal conferral. Finalized mastery and course records retain their durable history protections.

### Faculty Portal

- `/faculty` requires an active account, active institution and membership, and an explicitly linked Faculty identity. The linked Student identity and registrar permissions are evaluated independently.
- **My Mentees** requires an explicit same-institution assignment managed from the Faculty edit page. It shows the assigned student's program, competency requirements, supporting course evidence, and recorded section Master Assessment history. **My Courses** uses active or assigned teaching records to show the matching enrollments, current progress evidence, and finalized course outcomes.
- My Mentees offers competency mastery finalization for eligible passed attempts. My Courses remains a read-only view. See [ADR 0004](docs/adr/0004-scope-faculty-portal-access-to-academic-assignments.md) for the assignment and access boundaries.

### Course Achievement and Competency Attainment

- Traditional course requirements use eligible Academic Records and the configured course, credits, and grading rules. Guarded enrollment completion remains responsible for recording the course outcome.
- A course may provide learning evidence for one or more competencies. A competency requires demonstrated mastery through its applicable Master Assessment. Course completion, course substitutions, generic approved evidence or waivers, and section assessment passes alone cannot establish program competency attainment.
- Registrar administrators map assessments to competency requirements from the program requirement group. The many-to-many mapping uses explicit identifiers and database constraints for requirement type and institution. The same assessment can map to several competencies, independently of each requirement’s optional supporting course.
- Finalization requires an active Master Assessment, a recorded passed attempt, and an active mentorship in the current institution. It captures only active mapped competencies in the student’s assigned program and preserves the original assessment, evidence, mentor, and decision time. A repeated request returns the saved decision. Removing a mapping affects future decisions and preserves attempts and settled mastery.
- Degree audit reads finalized mastery separately from course achievement and awards no course credits or grade points for mastery. Active unverified requirements block conferral. Conferral snapshots retain the mastery evidence used in the eligibility decision.
- Section progress and program competency attainment are separate decisions. See [ADR 0003](docs/adr/0003-distinguish-course-achievement-from-competency-attainment.md) and the [section progress architecture](docs/CLASS-SECTION-PROGRESS-ARCHITECTURE.md#master-assessment).

## Academic Terms

- `AcademicTerm` is an institution-scoped core academic operations model. Its existing institution scope and relationships remain authoritative.
- It is the canonical scheduling context for `CourseOffering`, `CourseEnrollment`, `TeachingAssignment`, `AttendanceSession`, `AcademicRecord`, and transcript grouping. Direct enrollment-to-term relationships support legacy/manual enrollment without a section.
- `CourseOffering` remains the operational Class Section model; it is not replaced or renamed.
- `Catalog` uses `academic_year` and effective dates independently. No direct Catalog-to-AcademicTerm coupling is established.

### Labels, selection, and vocabulary

- `AcademicTerm.display_label` supplies live display text as `{name} ({academic_year})`.
- `orderedForSelection()` orders terms by academic year descending, then start date ascending. Chronological transcript grouping retains its own reporting order.
- `AcademicTerm::termTypeOptions()` centralizes the calendar category vocabulary: `fall`, `spring`, `summer`, `winter`, `intensive`, `module`, `custom`. These describe specific registrar terms, not calendar systems such as `semester`.
- `AcademicTerm::statusOptions()` centralizes `draft`, `open`, `active`, `completed`, `archived`. Status is registrar-managed and never automatically derived from instructional or registration dates.
- Type and status remain strings. No enum conversion, database constraint, normalization, or historical data rewrite is introduced.

### Calendar boundaries and overlap

- Term start/end dates are registrar calendar boundaries; the form requires end on or after start.
- Section start/end dates represent actual instruction and normally fall within the term. Existing `CourseOffering` boundary helpers and form notices warn about dates outside the term without blocking persistence.
- Registrar-approved exceptions remain available for intensives, modules, practica, make-up sessions, imported historical records, and similar cases.
- Terms may overlap within an institution, and multiple terms may be active simultaneously. There is no global single-current-term assumption or automatic status synchronization.

### Ordinary registration windows

- Optional `registration_start_date` and `registration_end_date` define ordinary registration timing independently of instructional dates and status. Registration may occur outside instructional dates.
- Both dates absent means no automated window is configured, not closed registration. `hasRegistrationWindow()` reports whether at least one boundary is present; `isRegistrationOpenOn()` returns `null` when neither is configured.
- A start-only window has no configured closing boundary; an end-only window has no configured opening boundary. With a configured window, `isRegistrationOpenOn()` returns a boolean. `isBeforeRegistrationWindow()` and `isAfterRegistrationWindow()` evaluate only their respective configured boundary.
- Evaluation accepts an explicit Carbon date, compares calendar dates (ignoring time of day), and includes both opening and closing dates. Callers supply dates in their intended calendar context; registrar screens use the application's current date.
- The term form permits missing boundaries and requires registration end on or after registration start only when both are supplied. It does not constrain registration dates to instructional dates.
- `registrationWindowLabel()` provides shared advisory state text. The Academic Terms table displays today's state and registration dates. The enrollment form shows the selected term's dates and today's state, including legacy/manual enrollments. This is current calendar visibility, not an evaluation of historical `enrolled_at`.
- Authorized registrars and administrators may create and edit enrollments before opening or after closing. These helpers neither authorize nor block persistence; no override field, timer, job, automatic status transition, or add/drop deadline is introduced.
- Student self-service enforcement, late-registration approval, and add/drop policies require separate scope and registrar decisions.

### Durable records

- Official transcript lines retain both `academic_term_id` and denormalized `term_label`. Issuance captures snapshot text; issued transcript rendering uses that durable text and never substitutes a live display accessor.
- Term refinement does not regenerate transcript snapshots, rewrite academic records, or alter guarded completion behavior.

## Course Enrollments

- `CourseEnrollment` remains the single enrollment model and supports both CourseOffering-backed and direct/manual workflows.
- `Course.scheduling_basis` is a lightweight string semantic with centralized options: `term` (the default) and `completion_date`. Kairos courses use `completion_date`; existing courses retain term behavior unless explicitly reclassified.
- Term-bound enrollments use their direct AcademicTerm context. Completion-date grouped enrollments may keep `academic_term_id` null permanently; completion does not mutate the enrollment merely to support reporting.
- Completion-date grouping uses the durable AcademicRecord/enrollment completion date. It is reporting context and does not convert the enrollment into a term-bound record.
- Automatic reporting candidates are only standard `fall`, `spring`, `summer`, and `winter` terms. `intensive`, `module`, and `custom` terms do not automatically claim completion-date coursework.
- Exactly one standard term must contain the completion date inclusively. Zero matches return `no_match`; multiple matches return `ambiguous`. Both outcomes are explicit registrar-review conditions and never choose an arbitrary overlapping term or create a term.
- Official transcript issuance resolves completion-date records before creating lines. Unresolved records prevent silent issuance; issued `term_label` values remain durable snapshots and are never re-resolved during rendering.
- AcademicRecord remains durable and may have a nullable `academic_term_id` for non-term completion. Completion continues through `EnrollmentCompletionService`, with existing guarded eligibility and audit snapshots.

## Faculty and Teaching Assignments

- `Faculty` is institution-scoped academic personnel identity and is separate from `User` authentication. Faculty records do not require login accounts. The Faculty Portal resolves explicitly linked Faculty records through the shared Laravel session.
- `TeachingAssignment` is the instructional assignment record. `CourseOffering` remains the operational Class Section context, and offering-backed assignments inherit its course and AcademicTerm context.
- Faculty status and assignment role/status vocabularies are centralized as model string options. Status changes are administrative and do not automatically transition or delete assignments.
- Multiple faculty assignments and roles remain supported; no new primary-instructor uniqueness policy is inferred.
- Teaching assignments may be direct/manual. Completion-date courses may retain a null `academic_term_id`; faculty administration does not force a conventional term onto non-term coursework.
- Faculty and assignment history remains inspectable through soft-delete/inactive semantics. Routine hard-delete actions are not exposed where foreign-key cascades would erase historical assignment context.

## Applicant-to-Student Conversion

- `Applicant` is the durable admissions record and remains preserved after conversion. `Student` is the academic identity created for accepted applicants; the records are linked through the unique `students.applicant_id` relationship.
- Conversion is available only for accepted applicants and runs transactionally. It preserves institution, program, name, contact, and admissions notes, assigns an active student number, and records the conversion date without creating courses, enrollments, academic records, or transcript lines.
- Conversion is idempotent: a linked Student is returned on repeat attempts, while an existing same-institution student email requires registrar review instead of guessing or creating a duplicate.
- Applicant status changes to `enrolled` only after the Student exists. The Applicant record and its historical status/conversion timestamp remain inspectable through the registrar UI.
- Conversion does not assign an AcademicTerm or infer academic standing. Subsequent academic enrollment and durable academic records remain separate registrar actions.

## Academic Records

- `AcademicRecord` is the durable academic outcome for completed coursework. `EnrollmentCompletionService` is the normal creation path, and its enrollment-level guard prevents a second record for the same `CourseEnrollment`.
- Completion copies historical snapshots such as course code/title, attempted and earned credits, final grade, grade label, grade points, credit/pass/GPA flags, status, and completion date. These values remain authoritative even if Course, GradeValue, GradeScale, AcademicTerm, Student, or CourseEnrollment data later changes.
- Foreign keys to Student, Course, AcademicTerm, GradeScale, GradeValue, and CourseEnrollment provide navigation and provenance. They do not authorize rewriting the stored academic outcome. Historical or imported records may remain detached from an enrollment where the nullable relationship supports that existing use.
- Term-bound records preserve the completed enrollment's direct `academic_term_id`. Completion-date records may retain a null term and use `ReportingTermResolver` only for contextual reporting from durable `completed_at`; resolution never mutates the AcademicRecord or creates a fake term.
- `AcademicRecord` and `OfficialTranscriptLine` are separate durable layers. Transcript issuance reads AcademicRecord and snapshots the resolved label and outcome into OfficialTranscriptLine; later AcademicRecord or upstream changes do not rewrite issued lines.
- Ordinary registrar edit surfaces protect completion-derived fields after creation. Notes remain administrative metadata. Existing manual/import creation remains available for historical records; future correction of finalized outcomes should use an explicit amendment workflow rather than silent edits.

- Only guarded enrollment completion may set the completed status and completion date through registrar enrollment workflows. Ordinary enrollment edits retain administrative notes after completion or once a durable outcome is linked. Existing records that indicate completion remain protected even when their outcome is missing.
- Preserve academic history when administering students, courses, and institutions. Academic Records cannot be deleted through ordinary administration, and registrar deletion is blocked when it would hide protected student history or cascade through protected academic records or transcripts. Legacy soft-deleted records count toward retention. A student with an official transcript request is protected while that request can still be finalized. Manual historical AcademicRecord entry remains available, including explicit matching of a historical completed enrollment that lacks its outcome.

- Database retention covers academic records, their linked enrollments, and official transcript rows and lines, including soft-deleted history. Required parents and linked completion provenance use restrictive foreign keys. Nullable reporting and grading references may clear while their captured values survive. Database triggers reject hard deletion of outcomes and transcript lines, and reject deletion of issued transcript history through bulk or query-builder operations. Draft line replacement can still soft-delete lines before issuance. A legacy completed enrollment without an AcademicRecord is protected from direct and cascading deletion. See [the retention migration and deployment notes](docs/ACADEMIC-HISTORY-RETENTION.md).

## Degree Conferral

See [ADR 0001](docs/adr/0001-record-degree-conferral-as-a-durable-award.md) for the decision and consequences.

- The degree audit is a current-state review of the student's assigned program. Its result supplies evidence for a registrar decision. Conferral requires verified complete active groups and coverage of the active requirements. The workflow blocks configurations the audit cannot verify, including per-requirement minimum grades, unsupported or ungrouped requirements, disallowed substitutions, and ambiguous credit allocation. Existing approved evidence, waivers, and permitted approved substitutions can satisfy requirements. Conferral offers no additional exception path.
- An authenticated institutional administrator with an active `admin` or `super_admin` membership in the selected student institution and permission to edit the student may use **Confer Degree**. Showing the audit is advisory. Confirmation records the decision only after a transaction locks the student and rechecks the program, actor, active membership, eligibility, and prior awards.
- `DegreeConferral` stores the institution, student, program, program code and title, credential designation, official date, actor, decision timestamp, registrar remarks, and a snapshot of the evaluated requirements and evidence. One durable award is allowed per student and program because a program currently represents one credential. A student can earn awards for multiple programs. The database prevents duplicate inserts, edits, hard deletes, and parent cascades. There is no ordinary correction or revocation workflow.
- Conferral leaves global Student status and program assignment unchanged. A manually maintained `graduated` status does not establish an award. Concurrent program participation, curriculum versions, repeat awards for one program, institution-wide GPA and residency rules, honors, date review, delegated approval, and correction or revocation require institutional policy and any supporting schema.
- Future official transcripts should copy the award's identifier, program code/title, credential designation, and official conferral date at issuance, with an institutionally approved rule for which awards to include. Reprints should read that transcript snapshot. Registrar notes and eligibility evidence stay internal unless policy explicitly requires disclosure. Existing issued transcripts retain their historic content.

## Transcript Engine

> Official academic transcript issuance is an atomic, controlled operation. A transcript may enter its issued state only through the authorized finalization workflow, which validates eligibility and preserves the academic data associated with the issuance. Ordinary administrative editing may not bypass this process.

- `AcademicRecord` is live source history. **Issue Transcript** locks the transcript, rechecks its eligibility and institutional context, and saves the issued metadata, `OfficialTranscriptLine` rows, and versioned `OfficialTranscript.document_snapshot` in one transaction. A failure rolls back every write, and competing requests preserve the first successful issuance.
- The document snapshot captures the printed institution and student identity, student number and enrollment date, applicable program code, title and credential, effective settings and labels, GPA summaries, and a place for authorized awards. Existing issued number, date, purpose, recipient and delivery fields remain authoritative. New issued rendering reads the saved document and its lines. Registrar and internal notes remain editable administrative metadata outside the document. Database guards freeze issued document fields and lines against updates and late inserts while preserving nullable provenance links.
- Term-bound records use their direct AcademicTerm. Completion-date records use `ReportingTermResolver` and durable `completed_at`; specialized terms are ignored, and no-match or ambiguous results block issuance. Lines capture reporting-term identity, label, and sort order. New issued documents group by the frozen term identifier, so equal labels remain distinct even if an AcademicTerm is renamed or deleted.
- Lines preserve the original `affects_gpa` decision, grade points, and credits. GPA summaries use the same weighted calculation as the live GPA preview and record cumulative and term results plus counts of incomplete GPA-bearing lines. The existing grade-points setting controls line display. Publication of aggregate GPA requires an explicit setting and institutional policy for incomplete records.
- The awards list starts empty until an institution approves which durable `DegreeConferral` records may appear, including whether to include all programs or only the applicable program and whether the issue date limits inclusion. The versioned payload accepts approved award identifiers, captured program code/title, credential and conferral dates. Rendering uses saved award fields when present. Student status and current program labels never determine awards.
- The **Legacy issued transcripts** filter identifies records without a document snapshot. Their view labels the missing capture explicitly and shows surviving issued number, date and academic lines. Optional settings-dependent details and missing historical identity or GPA are omitted. Legacy term groups use their surviving term ID, then label where no ID survives. Existing records are preserved without backfill. The **Issued without snapshot lines** filter remains available for registrar review.
- Student transcript and GPA previews remain advisory and use current records.
- The issued preview and authenticated print page share one Blade document. The print page reads the same immutable source and uses native browser Save as PDF with local print CSS and fonts. Export access requires an active membership in the selected institution and the existing record-view permission. See [official transcript print and export](docs/OFFICIAL-TRANSCRIPT-PRINT.md).

### Intentionally deferred

- Student self-service availability and separate late-registration/add/drop policies.
- Enum or database constraint changes only if a later architectural decision requires them.
- Additional seed/demo terms, overlap visibility, or workflow-specific active/date scopes when needed.
- Catalog mapping requires a separate architectural decision; current independence remains intentional.
