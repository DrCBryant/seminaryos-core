# ADR 0001 · Record degree conferral as a durable award

Status — Accepted

## Context

The degree audit evaluates current academic information, and the Student status describes administrative standing. Program titles, requirement definitions, and student program assignments can change after a student earns a credential. An institution needs a durable record of the award it actually authorized.

## Decision

Use the existing degree audit as eligibility evidence for an explicit, institution-authorized decision. Record the decision in an immutable DegreeConferral with captured program identity, conferral date, actor, and eligibility evidence. Store one award per student and program under the current model, where each program represents one credential. Later changes to the program, audit evidence, or Student status leave the award intact.

## Consequences

A student can hold awards from several programs and remain enrolled. Repeat awards within one program, curriculum versions, delegated approvals, and award corrections require separate institutional policy. Future official transcript issuance must copy approved award fields into a transcript snapshot so reprints reproduce the issued document. Once awards exist, rollback of the award table requires a forward migration that preserves those records.
