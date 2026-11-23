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

Registrar administrators manage a many-to-many mapping between a `competency` requirement and a Master Assessment in the same institution. Mapping uses explicit identifiers independently of the supporting course and never derives mappings from historical names or course records.

Faculty Mentors hold final academic authority for their assigned mentees. An authorized mentor can review an existing submitted attempt on an active mapped Master Assessment and explicitly finalize Passed, Revision Needed, or Failed with feedback. A Passed result records mastery in the same transaction. An authorized mentor can also finalize mastery from an existing passed attempt. This records mastery only for the explicitly mapped active competencies in the student's assigned active requirement groups. A passing section result alone only contributes to section progress. Course Instructors retain authority for assigned traditional courses through existing grading and guarded completion workflows. The Registrar verifies overall eligibility and authorizes formal conferral.

## Consequences

Result entry preserves the submitted evidence and records one immutable mentor outcome per attempt, including unsuccessful outcomes. Mastery finalization creates one immutable decision per passed attempt, with the responsible mentor, decision time, source assessment and attempt evidence, and the exact competencies credited. Retries return the original decision. Later mapping or source edits do not rewrite it. Mapping removal preserves attempts and prior decisions. Database retention protects the decision and its source records from deletion.

Degree audit immediately recognizes a finalized decision for the student's program. Unverified competencies still block formal conferral, and a conferral snapshot captures the mastery decisions used in the audit. Competency mastery does not generate course credit, grade points, or an Academic Record.

Current Master Assessments and their attempts remain section-bound, even when an attempt has no course enrollment. Section-independent assessment needs its own explicit context and evidence rules before implementation. Mastery thresholds, competency grading scales, reassessment limits, and a correction process that preserves historical decisions remain institutional policy work.
