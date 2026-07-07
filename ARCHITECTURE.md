# SeminaryOS Architecture Notes

## Academic Terms

- [`AcademicTerm`](app/Models/AcademicTerm.php) is institution-scoped and belongs to the core academic operations domain.
- [`AcademicTerm`](app/Models/AcademicTerm.php) is the canonical scheduling boundary for offerings, enrollments, teaching assignments, attendance sessions, academic records, and transcript grouping.
- [`CourseOffering`](app/Models/CourseOffering.php) remains the operational class section model and must not be renamed or replaced by [`AcademicTerm`](app/Models/AcademicTerm.php).
- [`Catalog`](app/Models/Catalog.php) currently uses `academic_year`, `effective_start_date`, and `effective_end_date` independently. Do not introduce a direct Catalog-to-AcademicTerm relationship without a separate architectural decision.
- [`OfficialTranscriptLine`](app/Models/OfficialTranscriptLine.php) stores both `academic_term_id` and denormalized `term_label`. The `term_label` value is durable snapshot text and should not be casually regenerated after transcript issuance.

### Known Academic Terms Follow-ups

- Normalize the canonical `term_type` vocabulary.
- Reduce duplicated term selector ordering and label formatting.
- Clarify whether offering dates must remain inside term dates or may intentionally differ.
- Clarify whether overlapping active terms are allowed for intensives or modules.
- Clarify catalog-to-term mapping.
- Add seed/demo academic terms where appropriate.
