# SeminaryOS Architecture Notes

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

- `Faculty` is institution-scoped academic personnel identity and is separate from `User` authentication. Faculty records do not require login accounts, and Faculty Portal authentication is deferred.
- `TeachingAssignment` is the instructional assignment record. `CourseOffering` remains the operational Class Section context, and offering-backed assignments inherit its course and AcademicTerm context.
- Faculty status and assignment role/status vocabularies are centralized as model string options. Status changes are administrative and do not automatically transition or delete assignments.
- Multiple faculty assignments and roles remain supported; no new primary-instructor uniqueness policy is inferred.
- Teaching assignments may be direct/manual. Completion-date courses may retain a null `academic_term_id`; faculty administration does not force a conventional term onto non-term coursework.
- Faculty and assignment history remains inspectable through soft-delete/inactive semantics. Routine hard-delete actions are not exposed where foreign-key cascades would erase historical assignment context.

### Intentionally deferred

- Student self-service availability and separate late-registration/add/drop policies.
- Enum or database constraint changes only if a later architectural decision requires them.
- Additional seed/demo terms, overlap visibility, or workflow-specific active/date scopes when needed.
- Catalog mapping requires a separate architectural decision; current independence remains intentional.
