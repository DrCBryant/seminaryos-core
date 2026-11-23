# ADR 0004 · Scope Faculty Portal access to academic assignments

Status — Accepted

## Context

Faculty identity and teaching assignment gates already exist. A teaching assignment can carry the Mentor label, while that record describes a course or section and has no student roster. Faculty mentorship and teaching can both apply to the same person and institution.

## Decision

Use an explicit institution-scoped link between a Faculty record and a Student record. Composite foreign keys require both identities to belong to the same institution. Administrators manage each pair through the existing Faculty edit page. Each pair grants access to that student's limited mentorship view while the link and institutional account are active. A student may be linked to multiple faculty members.

Reuse active or assigned TeachingAssignment records for instructional views. An exact section assignment scopes access to that section. A direct assignment scopes access to sectionless enrollments for its course and matching academic term, with a missing term matching only sectionless enrollments without a term.

## Consequences

A mentorship does not grant teaching access. Teaching access does not grant mentorship or access to a student's complete academic history. Removing a mentorship revokes the corresponding view and preserves academic evidence. Student, Faculty, and authorized Registrar experiences share one session. Mentor mastery finalization follows the institutional rule in [ADR 0003](0003-distinguish-course-achievement-from-competency-attainment.md).
