# real-upstream-corpus-upsert-returning-dynamic-20260531T064006Z-0 blocked

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test`

Current blocker:

- This worktree already contains accepted or current-base focused files for the
  obvious non-overlapping UPSERT/RETURNING dynamic corpus sections:
  `upsert1` through `upsert5`, `upsertfault`, `returningfault`,
  `returning1-1.0` through `17`, `returning1-18.1`, `returning1-19.1`,
  `returning1-20.1` through `20.3`, and `returning1-21` through `24`.
- The existing coverage includes conflict-target admission, composite and
  reversed unique targets, table named `excluded`, aliased target WHERE
  behavior, multi-arm and catch-all priorities, omitted target behavior,
  SELECT-source UPSERT, trigger order, fault cleanup, DDL error ordering,
  correlated RETURNING subqueries, writable schema, recursive trigger
  visibility, and virtual-table RETURNING behavior.
- Adding another patch in this exact micro-slice would either repeat an existing
  real upstream scenario or create generated PASS-line inflation around already
  covered bookkeeping. That fails the hard handoff floor for
  `real-upstream-corpus-*` slices.

Next larger batch to try:

- Reassign a broader real upstream DML fault or trigger-returning slice outside
  this exhausted UPSERT/RETURNING dynamic bucket, for example a combined
  `insertfault.test`, `updatefault.test`, `deletefault.test`, trigger/FK
  fault-cleanup batch, or a current failing root/admission blocker that unlocks
  at least 2,000 focused PASS cases or 10,000 behavior assertions.

Dependency closure:

- No new support component is needed for this blocked note. The block is
  overlap/exhaustion of the assigned upstream section, not a missing native PHP
  dependency.
