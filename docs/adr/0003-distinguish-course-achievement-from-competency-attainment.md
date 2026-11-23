# ADR 0003 · Distinguish course achievement from competency attainment

Status — Accepted

## Context

| Existing records and services | Responsibility |
| --- | --- |
| `Program`, `Course`, `ProgramRequirementGroup`, `ProgramRequirement` | Organize curriculum, group requirements, and describe the conditions for degree audit. |
| `CourseEnrollment`, `AcademicRecord`, `EnrollmentCompletionService` | Record enrollment and create durable course outcomes through guarded completion. |
| `MasterAssessment`, `StudentMasterAssessmentAttempt`, `SectionProgressEvaluator` | Record section assessments and student outcomes and evaluate them as section progress evidence. The enrollment link on an attempt is optional, while the section is required. |
| `StudentRequirementEvidence`, `DegreeAuditPreview` | Track manual evidence and evaluate course records, eligible evidence, and substitutions against curriculum rules. Generic evidence has no verified link to a Master Assessment. |
| `Faculty`, `TeachingAssignment`, `AcademicIdentityAccess` | Resolve the faculty identity and authorize viewing assigned teaching contexts in the active institution. The teaching-assignment Mentor label has no student mentorship relationship. |

## Decision

Use the existing curriculum model to describe both traditional course requirements and explicit `competency` requirements. Courses earn grades and credits under existing completion rules. One course may supply learning evidence for several competencies. Competency attainment requires a verified mastery decision from the applicable Master Assessment. Only that decision can satisfy a program competency requirement.

Use an explicit student mentorship assignment for Faculty Mentor assessment authority and an applicable teaching assignment for Course Instructor grading authority. Those responsibilities can coexist with each other and with a Student identity. Assessment entry must reuse existing grading, evidence, and guarded completion workflows. Registrar review and finalization follow explicit institutional policy, and finalized academic history retains its existing protections.

## Consequences

The degree audit marks competency requirements as unevaluated before considering course matches, substitutions, or generic manual evidence. These requirements cannot advance degree conferral while unverified. Section assessment passes still contribute to section progress. Existing requirement types, course records, transcript snapshots, and conferred awards retain their current behavior. Existing curriculum rows are not reclassified automatically.

Before implementing program-level mastery or faculty assessment entry, institutions must define how master assessments map to competencies, which rubric and result establish mastery, whether and how to assess outside a course section, who provisions and revokes student mentorship, which teaching assignments grant course grading authority, and how registrar review and finalization work. Existing section assessment data and faculty read permissions do not establish those decisions.
