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

### Intentionally deferred

- Student self-service availability and separate late-registration/add/drop policies.
- Enum or database constraint changes only if a later architectural decision requires them.
- Additional seed/demo terms, overlap visibility, or workflow-specific active/date scopes when needed.
- Catalog mapping requires a separate architectural decision; current independence remains intentional.
