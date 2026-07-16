# real-upstream-corpus-upsert-returning-dynamic-20260530T185028Z-0

Status: blocked for ready handoff; no new behavior patch emitted.

Accepted base inspected: `0eff666a68d9fc5c2de0693a82870643615fd7c5`.

Assigned upstream area inspected:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Current-base overlap found:

- `lanes/libsqlite/src/SQLiteUpsertReturningDynamicCorpusPlan.php` already
  includes upstream-backed `upsert4.test`, `upsert5.test`, `upsert2.test`, and
  `returning1.test` scenario data for dynamic UPSERT/RETURNING behavior.
- Existing focused tests already cover dynamic multi-arm UPSERT conflict
  selection, schema variants, catch-all arm priority, redundant conflict arms,
  dynamic target analysis, SQL dynamic execution, correlated statements,
  statement-level RETURNING projection, and `upsert5` full/conflict matrix
  behavior.
- Focused overlap check passed:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamic*.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert5*.php`
  reported `14 test files, 44877 assertions, 0 failures`.

Reason for blocker:

The hard floor for `real-upstream-corpus-*` slices requires at least one of
1000 newly added distinct focused TestRunner PASS cases, 5000 new behavior
assertions, a named blocker fix that unlocks at least 2000 PASS cases or 10000
assertions, or real mapped denominator movement. The assigned
UPSERT/RETURNING dynamic surface is already heavily covered on this base, and a
small additive file here would be duplicate or low-yield rather than
countable new upstream behavior.

Next larger batch to try:

Retarget a fresh `real-upstream-corpus-*` worker away from already-counted
dynamic UPSERT/RETURNING and toward a cross-file RETURNING error/trigger batch
from real upstream `returning1.test` sections 6 through 13 plus
`returningfault.test`, specifically:

- RETURNING name-resolution errors for `old.*`, `new.*`, table aliases, and
  `UPDATE ... FROM` source-table references.
- RETURNING on views and TEMP trigger interactions.
- RETURNING constraint ordering where FK/check failures prevent row yield.
- Fault-path RETURNING cleanup from `returningfault.test` if the current
  harness can model it without fabricating script ids.

That follow-up should be built as one non-overlapping batch and should only be
marked ready if it reaches the current hard floor or fixes a concrete parser /
executor blocker that unlocks the full batch.

Dependency closure:

No new support component was needed for this audit. The block is coverage
overlap and handoff-floor economics, not a missing PHP dependency.
