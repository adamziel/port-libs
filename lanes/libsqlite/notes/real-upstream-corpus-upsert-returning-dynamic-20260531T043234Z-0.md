# real-upstream-corpus-upsert-returning-dynamic-20260531T043234Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - `upsert3-130`: `ON CONFLICT(k,v)` matches a composite unique index.
  - `upsert3-140`: `ON CONFLICT(v,k)` matches the same composite unique index.
  - `upsert3-200`: a table literally named `excluded` still treats `excluded.column` in the update arm as the incoming row.
  - `upsert3-210`: a target alias such as `base` names the current row while `excluded` names the incoming row for `WHERE` gating.

## Handoff

Added `SQLiteRealUpstreamUpsert3ReturningCompositeDynamicTest.php`.

The focused test ports the real upstream `upsert3.test` composite conflict-target
and `excluded` name-resolution behavior into a generic application table named
`excluded` with `tenant_id, slot_id` uniqueness. Each dynamic case executes six
native UPSERT statements with `RETURNING`, compares the native final row images
and RETURNING streams to an in-memory SQLite oracle, and includes the malformed
single-column target rejection from the same upstream family.

Focused movement:

- `1002` distinct TestRunner PASS cases.
- `2005` focused assertions.
- Non-overlap: this owns `upsert3.test` composite-target order and table-named
  `excluded` alias behavior. It does not repeat the accepted `upsert4`
  conflict/move matrix, `upsert5` redundant conflict/index scan matrix,
  `returning1-17` repeated-row RETURNING stream, or the 2026-05-31 extended
  UPSERT yield matrix.

Dependency closure: no new support component needed. The slice reuses the
existing native `SQLiteUpsertReturningSql` executor and uses PDO SQLite only as
a local oracle inside focused tests.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert3ReturningCompositeDynamicTest.php`
  - `1 test files, 2005 assertions, 0 failures`
