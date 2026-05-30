# real-upstream-corpus-upsert-returning-dynamic-20260530T182432Z-0

Status: blocked by current-base overlap.

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`

Current accepted base `f9e4e2d5498742752e9304fb10cad66aa60851fc` already
contains this assigned behavior family:

- `SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php` covers
  `returning1.test` multi-row UPSERT RETURNING rowids and correlated RETURNING
  subquery recomputation.
- `SQLiteRealUpstreamUpsertReturningDynamicSchemaVariantsTest.php` covers
  `upsert5.test` generalized multi-arm UPSERT behavior across rowid,
  explicit-primary-key, reversed-column, and WITHOUT ROWID table layouts.
- `SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php` covers
  `upsert4.test` null/partial conflict handling, REPLACE ordering, and
  RETURNING projection cases through the shared dynamic corpus plan.
- `SQLiteRealUpstreamUpsertReturningDynamicTargetTest.php` covers
  `upsert4.test` sections 1 through 5 for target analysis, expression-index
  conflict targets, partial-index targets, and unmatched target diagnostics.
- `SQLiteRealUpstreamUpsertReturningDynamicTest.php` covers later
  `upsert4.test` sections 6 through 9 for ON CONFLICT precedence over REPLACE,
  excluded/table-alias semantics, table named `excluded`, and trigger repeated
  UPSERT histogram behavior.

This worker therefore cannot honestly satisfy the current throughput handoff
floor by adding another small `upsert`/`returning` variant: it would overlap
accepted behavior and inflate PASS lines without a new upstream section.

Next larger non-overlapping batch to try:

- Move out of the already-covered `upsert4`/`upsert5` dynamic matrix and port a
  distinct upstream DML cluster, such as unclaimed `returning1.test` later
  trigger/view/error sections or `upsertfault.test`/`returningfault.test`
  fault-path behavior, but only if the PHP port has a real native behavior or
  runner blocker to exercise.
- If the goal remains mapped denominator growth rather than PHP PASS-line
  growth, run a guarded upstream-runner admission slice against the hydrated
  fault tests and count only real runner evidence.

Focused overlap check:

- `rg -n "upsert4|upsert5|returning1" lanes/libsqlite/tests lanes/libsqlite/src`
  shows the current-base focused PHP coverage listed above.

Dependency closure: no new support component is needed; the blocker is
assignment overlap on the accepted base, not a missing native dependency.
