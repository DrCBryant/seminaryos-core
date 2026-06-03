# Catalog Engine

## Purpose

The catalog engine is responsible for turning institution-scoped academic source records into publishable catalog pages without making catalog pages the primary source of truth.

Primary source records:
- `programs`
- `courses`
- `catalogs`

Generated projection records:
- `catalog_pages`

The catalog engine must support draft editing, previewable generated content, controlled publishing, and future export into PDF catalogs.

---

## Core Principles

1. Programs and courses remain the authoritative academic records.
2. Catalog pages are generated derivatives used for catalog presentation and publishing.
3. Generation is institution-scoped and catalog-scoped.
4. A catalog may represent a versioned academic publication context.
5. Publishing should be explicit and reversible without mutating the underlying academic source records.

---

## Catalog Generation Workflow

### High-level workflow

1. An institution creates or updates a catalog record.
2. Source records are selected from institution-owned `programs` and `courses`.
3. The engine generates normalized catalog page payloads.
4. The engine upserts matching `catalog_pages` for the selected catalog.
5. Generated pages remain in draft until explicitly published.
6. Published pages become eligible for public routing and search indexing.

### Generation stages

#### 1. Source selection
- Load the target catalog.
- Load all source records belonging to the same institution.
- Filter by eligibility rules such as active status or explicit inclusion rules.

#### 2. Transformation
- Convert each source record into a page-specific view model.
- Resolve shared metadata such as title, slug, SEO defaults, publication status, and canonical path.
- Render body content or structured snapshot content.

#### 3. Persistence
- Create or update one `catalog_pages` row per generated page.
- Match existing generated records by `catalog_id` + source identity + page type.
- Preserve stable UUIDs once generated.

#### 4. Publication decision
- Leave generated records in draft by default.
- Permit bulk publish after editorial review.

---

## Program Page Generation

Each eligible program should generate a catalog page for the target catalog.

### Source inputs
- Program core fields such as title, code, description, credential type, credit hours, delivery method, and publication metadata.
- Related courses through `course_program`.
- Catalog-level framing such as academic year and active date range.

### Generated outputs
- `source_type = program`
- `source_id = programs.id`
- `page_type = program`
- Generated `title`
- Generated `slug`
- Rendered content snapshot summarizing the program for the catalog context
- SEO fields inherited from the program when available, otherwise generated from defaults

### Regeneration rules
- Regenerate when the source program changes.
- Regenerate when related curriculum mappings materially change.
- Regenerate when catalog-level naming or routing policy changes.

---

## Course Page Generation

Each eligible course should generate a catalog page for the target catalog.

### Source inputs
- Course core fields such as code, title, description, credit hours, delivery method, and publication metadata.
- Related programs when needed for context or backlinks.
- Catalog-level framing and effective dating.

### Generated outputs
- `source_type = course`
- `source_id = courses.id`
- `page_type = course`
- Generated `title`
- Generated `slug`
- Rendered content snapshot suitable for public catalog display
- SEO fields inherited from the course when available, otherwise generated from defaults

### Regeneration rules
- Regenerate when the source course changes.
- Regenerate when relationship data changes in a way that affects rendered output.
- Regenerate when catalog URL policy or template policy changes.

---

## URL Strategy

The URL strategy should distinguish between website CMS pages and generated catalog pages.

### Recommended structure

- Catalog landing page:
  - `/catalog/{catalog-slug}`
- Program catalog page:
  - `/catalog/{catalog-slug}/programs/{program-slug}`
- Course catalog page:
  - `/catalog/{catalog-slug}/courses/{course-slug}`

### Rules
- URLs are institution-scoped implicitly through the institution domain.
- `catalog_pages.slug` should be generated from the source record slug unless an explicit catalog override is ever introduced later.
- Slugs should be unique within a catalog and page path context.
- Canonical URLs should resolve to the published catalog route, not raw database identifiers.

### Why this structure
- It keeps catalog versions isolated.
- It makes program and course pages predictable.
- It avoids route collision with generic website CMS pages.

---

## SEO Strategy

SEO should be inherited from source records first, then supplemented by catalog-specific defaults.

### Recommended precedence

1. Page-specific override stored on generated `catalog_pages`
2. Source record SEO fields from `programs` or `courses`
3. Catalog-level fallback values
4. Website-level fallback values

### SEO requirements
- Use stable canonical URLs.
- Preserve descriptive titles based on program or course names.
- Generate meta descriptions from source summaries when explicit descriptions are absent.
- Prevent indexing of draft catalog pages.
- Optionally emit structured data later for academic offerings.

### Indexing policy
- Draft pages: `noindex`
- Published pages: indexable unless explicitly suppressed
- Superseded catalog versions: keep indexable only if intentionally preserved as public archives

---

## Publish Workflow

Publishing should be explicit and catalog-aware.

### Recommended workflow

1. Generate or regenerate draft catalog pages.
2. Review generated pages for completeness.
3. Publish the catalog record.
4. Publish all eligible draft `catalog_pages` belonging to that catalog.
5. Mark public routes as active.

### Publication behavior
- A page becomes public only when both the parent catalog and the page itself are published.
- `published_at` should record the page publication timestamp.
- Publish actions should be repeatable for regenerated drafts.

---

## Draft Workflow

Draft workflow should allow regeneration without immediately affecting public pages.

### Recommended behavior

- Generation writes draft records first.
- Editors can review content previews before release.
- A regenerated page should remain draft until approved for publication.
- Draft generation should not break currently published routes.

### Future enhancement path

- Support side-by-side draft vs published snapshots.
- Support dirty-state detection when source records have changed after publication.
- Support selective regeneration by program, course, or full catalog.

---

## Catalog Versioning

Catalogs should be treated as versioned publication containers.

### Versioning model

- Each `catalogs` record represents one publication edition or context.
- Versioning may be academic-year based, date-range based, or custom-label based.
- A source program or course may appear in multiple catalogs.

### Practical implications

- `catalog_pages` must be catalog-specific, not globally shared.
- Regeneration is always performed inside one target catalog context.
- Historical catalogs should remain reproducible after newer source changes when archival fidelity becomes important.

### Recommended near-term stance

- Allow multiple catalogs per institution.
- Treat one catalog as active for public routing when needed.
- Enforce at the application layer that only one catalog per institution may be marked active at a time.
- Validate through catalog services and admin workflows that activating one catalog deactivates or blocks any competing active catalog in the same institution.

### Application integrity note

- The relationship between [`course_program`](database/migrations/2026_06_02_000014_create_course_program_table.php), [`programs`](database/migrations/2026_06_02_000012_create_programs_table.php), and [`courses`](database/migrations/2026_06_02_000013_create_courses_table.php) must remain institution-consistent.
- This consistency is not additionally enforced at the database level in the current design.
- Application services and validation must reject any attempt to attach a course and program from different institutions or to persist a mismatched `institution_id` on the pivot row.
- Keep old catalogs available for future archive publication.

---

## Future PDF Catalog Generation

PDF generation should be a downstream export of catalog data, not a separate authoring system.

### Recommended future workflow

1. Select a published catalog version.
2. Pull all published catalog pages in deterministic order.
3. Render them through print-oriented templates.
4. Produce a generated PDF artifact.
5. Store the PDF as a versioned export tied to the catalog.

### Design implications now

- Keep generated catalog page content normalized and deterministic.
- Preserve ordering metadata for future table-of-contents generation.
- Keep catalog pages stable by UUID for export traceability.
- Avoid storing presentation-only logic exclusively in the web UI.

---

## Recommended Implementation Direction

1. Treat `programs` and `courses` as source-of-truth records.
2. Treat `catalog_pages` as generated, catalog-scoped projections.
3. Generate draft pages first.
4. Publish through explicit catalog-aware actions.
5. Reserve PDF generation as a later export layer built on the same generated page dataset.
