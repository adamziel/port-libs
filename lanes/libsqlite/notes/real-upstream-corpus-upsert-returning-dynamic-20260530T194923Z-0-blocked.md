# real-upstream-corpus-upsert-returning-dynamic-20260530T194923Z-0

Status: blocked for ready handoff; no duplicate behavior patch emitted.

Accepted base inspected: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

Assigned upstream source truth inspected:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Current-base overlap found:

- `SQLiteRealUpstreamUpsertReturningDynamicPriorityTest.php` already owns the
  broad `upsert1.test` multiple-constraint target-priority matrix with 1000+
  focused PASS cases and 19000+ assertions.
- `SQLiteRealUpstreamUpsertReturningDynamicArmsCorpusTest.php` and
  `SQLiteUpsertReturningDynamicCorpusPlan.php` already own `upsert2.test`
  trigger lifecycle behavior, `upsert4.test` null/replace behavior,
  `upsert5.test` generalized multi-arm priority and catch-all behavior, and
  `returning1.test` projection / constraint-order cases.
- `SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php` already owns the
  newer paired-yield block for `upsert5-1.x.100` through `upsert5-1.x.505`
  plus `returning1-4.2`, `returning1-4.5`, and `returning1-17`.
- The focused current-base overlap check passed:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamic*.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicTest.php`
  reported `16 test files, 45854 assertions, 0 failures`.

Reason for blocker:

The hard floor for a fresh `real-upstream-corpus-*` ready handoff requires at
least 1000 distinct focused TestRunner PASS cases, 5000 behavior assertions, a
named blocker fix that unlocks at least 2000 PASS cases / 10000 assertions, or
real mapped denominator movement. On this accepted base, the assigned
UPSERT/RETURNING dynamic surface is already covered above that scale. Adding
another small file would duplicate existing `upsert1` through `upsert5` and
`returning1` behavior rather than porting a fresh upstream section.

Next larger batch to try:

Retarget away from already-counted dynamic UPSERT/RETURNING matrices and build
a non-overlapping real upstream RETURNING error/fault batch from
`returning1.test` sections 6 through 13 plus `returningfault.test`, only if the
PHP port can model the behavior without metadata-only rows. The larger batch
should cover:

- RETURNING name-resolution errors for `old.*`, `new.*`, target aliases, and
  `UPDATE ... FROM` source-table references.
- RETURNING on view / TEMP-trigger paths not already covered by the dynamic
  UPSERT helpers.
- Constraint and fault paths where failures suppress RETURNING rows before a
  row is yielded.

Dependency closure:

No new support component was added. The blocker is assignment overlap and
handoff-floor economics, not a missing native PHP dependency.
