# SeminaryOS Architecture Notes

## Academic Terms

- [`AcademicTerm`](app/Models/AcademicTerm.php) is institution-scoped and belongs to the core academic operations domain.
- [`AcademicTerm`](app/Models/AcademicTerm.php) is the canonical scheduling boundary for offerings, enrollments, teaching assignments, attendance sessions, academic records, and transcript grouping.
- [`CourseOffering`](app/Models/CourseOffering.php) remains the operational class section model and must not be renamed or replaced by [`AcademicTerm`](app/Models/AcademicTerm.php).
- [`Catalog`](app/Models/Catalog.php) currently uses `academic_year`, `effective_start_date`, and `effective_end_date` independently. Do not introduce a direct Catalog-to-AcademicTerm relationship without a separate architectural decision.
- [`OfficialTranscriptLine`](app/Models/OfficialTranscriptLine.php) stores both `academic_term_id` and denormalized `term_label`. The `term_label` value is durable snapshot text and should not be casually regenerated after transcript issuance.

### Academic Term `term_type`

- [`academic_terms.term_type`](database/migrations/2026_06_15_000000_create_academic_terms_table.php) is currently a simple indexed string, and [`AcademicTerm`](app/Models/AcademicTerm.php) treats it as a fillable attribute without enum casting or model-level normalization.
- Current usage is mixed: the Academic Term Filament form and table filter present registrar-style term names such as `fall`, `spring`, `summer`, `winter`, `intensive`, and `custom`, while tests still create terms with `semester`.
- Near-term architectural rule: `term_type` should describe the registrar/calendar category of a specific term record, not the broader academic calendar system. Prefer concrete term labels such as `fall` or `spring` instead of structural labels such as `semester`.
- Canonical near-term vocabulary for documentation and future alignment is: `fall`, `spring`, `summer`, `winter`, `intensive`, `module`, and `custom`.
- Filament `term_type` options should be sourced from [`AcademicTerm`](app/Models/AcademicTerm.php) rather than duplicated in resource classes.
- Acceptable values remain simple strings for now. Do not introduce a PHP enum, schema constraint, or data rewrite until the vocabulary is stable enough to justify stricter typing and reporting rules.

### Known Academic Terms Follow-ups

- Align Filament options, tests, seeders, factories, and any validation with the documented `term_type` vocabulary.
- Treat existing `semester` usage in tests as follow-up cleanup rather than a behavior change in this documentation task.
- Decide in a later task whether `term_type` should remain free text or become constrained.
- Consider a PHP enum only after the `term_type` vocabulary proves stable and reporting needs justify it.
- [`academic_terms.start_date`](database/migrations/2026_06_15_000000_create_academic_terms_table.php) and [`academic_terms.end_date`](database/migrations/2026_06_15_000000_create_academic_terms_table.php) define the registrar calendar boundary for a term record.
- [`course_offerings.start_date`](database/migrations/2026_07_02_034000_create_course_offerings_table.php) and [`course_offerings.end_date`](database/migrations/2026_07_02_034000_create_course_offerings_table.php) define the actual instructional dates for a specific [`CourseOffering`](app/Models/CourseOffering.php).
- [`CourseOffering`](app/Models/CourseOffering.php) dates should normally fall within the related [`AcademicTerm`](app/Models/AcademicTerm.php) date range.
- SeminaryOS should allow exceptions for registrar-approved intensives, modules, practica, make-up sessions, imported historical records, and similar cases where section dates intentionally extend outside the term boundary.
- Future enforcement should begin with non-blocking warnings or review visibility in existing registrar surfaces before any hard validation is introduced.
- Hard blocking of out-of-term section dates requires a separate architectural decision and must not be inferred from the current schema, models, or forms.

- Reduce duplicated term selector ordering and label formatting.
- Clarify whether overlapping active terms are allowed for intensives or modules.
- Clarify catalog-to-term mapping.
- Add seed/demo academic terms where appropriate.
- Consider non-blocking term-boundary warnings on [`CourseOfferingForm`](app/Filament/Resources/CourseOfferings/Schemas/CourseOfferingForm.php) or related completion/review surfaces after the registrar rule is confirmed.
