# Academic history retention

Academic records, linked completion enrollments, and official transcript snapshots must survive parent deletion attempts. Retention also covers records already soft-deleted when the migration runs. The migration adds constraints and triggers without making business-data changes.

## Foreign-key inventory

The inventory covers outgoing and incoming links for `academic_records`, `official_transcripts`, `official_transcript_lines`, `course_enrollments`, `students`, `courses`, and `institutions`. Each name in the child column is a table unless a column is shown explicitly.

| Parent | Child columns | Before | After |
|---|---|---|---|
| Institutions | `academic_records.institution_id`, `official_transcripts.institution_id`, `official_transcript_lines.institution_id` | CASCADE | RESTRICT |
| Students | `academic_records.student_id`, `official_transcripts.student_id`, `official_transcript_lines.student_id` | CASCADE | RESTRICT |
| Courses | `academic_records.course_id` | CASCADE | RESTRICT |
| Course enrollments | `academic_records.course_enrollment_id` | SET NULL | RESTRICT |
| Official transcripts | `official_transcript_lines.official_transcript_id` | CASCADE | RESTRICT |
| Academic terms | `academic_records.academic_term_id`, `official_transcript_lines.academic_term_id`, `course_enrollments.academic_term_id` | SET NULL | SET NULL |
| Grade scales and values | Corresponding `academic_records.grade_scale_id` and `grade_value_id` | SET NULL | SET NULL |
| Academic records | `official_transcript_lines.academic_record_id`, `program_requirement_substitutions.academic_record_id` | SET NULL | SET NULL |
| Course offerings | `course_enrollments.course_offering_id` | SET NULL | SET NULL |
| Users | `course_enrollments.completion_reviewed_by_user_id` | SET NULL | SET NULL |
| Applicants and programs | Corresponding `students.applicant_id` and `program_id` | SET NULL | SET NULL |
| Institutions | `users.current_institution_id` | SET NULL | SET NULL |
| Institutions | `institution_id` on academic_terms, applicants, attendance_records, attendance_sessions, catalog_pages, catalogs, course_enrollments, course_offerings, course_program, courses, faculty, grade_scales, grade_values, institution_user, master_assessments, program_requirement_groups, program_requirement_substitutions, program_requirements, programs, section_assignments, student_master_assessment_attempts, student_requirement_evidence, student_section_manual_completions, student_section_submissions, students, teaching_assignments, transcript_settings, website_pages, websites | CASCADE | CASCADE |
| Students | `student_id` on attendance_records, course_enrollments, program_requirement_substitutions, student_master_assessment_attempts, student_requirement_evidence, student_section_manual_completions, student_section_submissions | CASCADE | CASCADE |
| Courses | `course_id` on attendance_sessions, course_enrollments, course_offerings, course_program, teaching_assignments | CASCADE | CASCADE |
| Courses | `program_requirements.course_id`, `program_requirement_substitutions.substitute_course_id` | SET NULL | SET NULL |
| Course enrollments | `course_enrollment_id` on attendance_records, student_master_assessment_attempts, student_section_manual_completions, student_section_submissions | SET NULL | SET NULL |

A legacy or imported AcademicRecord can still start with no enrollment link. An existing link preserves completion-review provenance, so deleting a linked enrollment is restricted even if the delete comes through a student, course, or institution cascade. Terms and grade definitions remain nullable references. Term labels, grades, credits, and other snapshot data survive when those references clear.

MySQL foreign-key cascades do not run child deletion triggers. Restrictive keys stop both direct parent deletion and indirect paths such as Institution → Student → Transcript and Course → Enrollment → AcademicRecord. A standalone draft without lines remains deletable. A parent with any transcript row is restricted, consistent with the registrar guard for pending transcript requests.

Triggers also cover direct writes that skip model events. Academic records reject hard and soft deletion. Transcript lines reject hard deletion and reject soft deletion once their parent indicates issuance. Transcripts indicating issuance reject both operations, including legacy issued rows with no lines. An issue timestamp or an issued, voided, or archived status is treated as historical evidence. Draft issuance can still replace old lines by soft deletion. Existing registrar guards remain in place. Unlinked incomplete enrollments retain their operational cascades. A legacy enrollment marked complete or carrying a completion date but lacking an AcademicRecord is guarded by direct and parent deletion triggers. The institution trigger covers enrollments reached indirectly through its students or courses. The legacy guard does not create missing outcomes. Privileged schema operations such as dropping or disabling constraints are outside this retention boundary.

## Deployment and rollback

1. Rehearse on a restored backup using the actual hosting MySQL version and deployment account. Confirm InnoDB, enabled foreign-key checks, standard foreign-key names and rules, and privileges to alter the tables and create and drop triggers. Check for existing trigger names or custom delete behavior. With binary logging enabled, confirm the hosting account can create triggers under the server's policy.
2. Check for orphaned references, including in soft-deleted rows. The migration checks each changed key for its expected parent, shape, delete rule, and orphans. SQLite additionally runs `PRAGMA foreign_key_check`. Investigate any mismatch before deployment. Preserve existing records during that review.
3. Back up the database and pause application writers for the migration. MySQL adds validated replacement keys before dropping the previous keys. These are separate DDL statements and can copy tables, consume temporary disk, and wait for metadata locks on related tables. Check table sizes, disk headroom, and lock duration in rehearsal.
4. If MySQL DDL fails partway, keep writers paused and inspect the actual constraints and triggers before recovery. MySQL does not roll back the whole migration as a unit. Resume from an operator-reviewed schema state or restore the backup. A blind retry will stop on unexpected key definitions.
5. A normal one-step rollback of the completed migration restores the original delete rules and removes its triggers without changing row contents. Keep writers paused during rollback because the former cascades become active again. Rollback cannot recover data deleted after those rules return.
6. SQLite rebuilds the affected tables in a transaction with foreign-key checks before and after. Its schema builder drops table triggers during rebuilds. Future SQLite migrations that rebuild these tables must preserve or recreate the retention triggers.

The migration was exercised on disposable MySQL 8.4.13 and SQLite databases. Actual ICDSoft schema, grants, data volume, and maintenance-window timing require validation before deployment.
