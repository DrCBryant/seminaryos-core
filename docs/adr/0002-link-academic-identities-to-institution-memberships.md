# ADR 0002 · Link academic identities to institution memberships

Status — Accepted

## Context

One Laravel account can belong to multiple institutions. A person can study and teach in the same institution, or represent different academic identities in different institutions. A single membership role cannot represent these independent academic relationships.

## Decision

Attach separate Student and Faculty links to the selected account's institution membership. Each link pairs the membership and the academic identity with the same institution under composite foreign keys. Membership roles continue to govern registrar administration. Portal actions require explicit ownership or a matching teaching assignment in the active institution.

## Consequences

A dual-identity user can move between eligible experiences in one session. Losing access to one link leaves the other intact. Membership suspension disables both links for that context, and physical account or membership removal clears the links while preserving the academic records. Both portal interfaces, their navigation, and rules for invitations or delegated identity administration remain separate work.
