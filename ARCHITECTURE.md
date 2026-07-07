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
- Acceptable values remain simple strings for now. Do not introduce a PHP enum, schema constraint, or data rewrite until the vocabulary is stable enough to justify stricter typing and reporting rules.

### Known Academic Terms Follow-ups

- Align Filament options, tests, seeders, factories, and any validation with the documented `term_type` vocabulary.
- Treat existing `semester` usage in tests as follow-up cleanup rather than a behavior change in this documentation task.
- Decide in a later task whether `term_type` should remain free text or become constrained.
- Consider a PHP enum only after the `term_type` vocabulary proves stable and reporting needs justify it.

- Reduce duplicated term selector ordering and label formatting.
- Clarify whether offering dates must remain inside term dates or may intentionally differ.
- Clarify whether overlapping active terms are allowed for intensives or modules.
- Clarify catalog-to-term mapping.
- Add seed/demo academic terms where appropriate.
