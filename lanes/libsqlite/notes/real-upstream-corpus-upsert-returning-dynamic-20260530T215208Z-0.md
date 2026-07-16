# real-upstream-corpus-upsert-returning-dynamic-20260530T215208Z-0

Ported a real upstream UPSERT/RETURNING target-priority cluster from the
hydrated SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
  sections `upsert1-700` through `upsert1-780`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  RETURNING row-yield semantics for changed rows.

Focused PHP coverage:

- Added `SQLiteRealUpstreamUpsertReturningDynamicTargetFirstTest.php`.
- 8,641 focused TestRunner PASS cases.
- 14,041 focused behavior assertions.
- Exercises targeted conflict priority when the incoming row conflicts with
  multiple unique constraints at once, including rowid, explicit unique-index,
  and WITHOUT ROWID table shapes.
- Verifies the selected target victim, unchanged non-target conflict rows,
  matched arm audit, RETURNING row image, RETURNING projection, changes/skips,
  before-image preservation, and rowid/without-rowid priority parity.

Non-overlap:

- This slice does not repeat accepted `upsert5` generalized arm ordering,
  `upsert2` DO UPDATE WHERE trigger traces, catch-all priority matrices,
  alias-scope matrices, no-target/tail behavior, trigger behavior, or broad
  RETURNING-only batches. It owns the `upsert1.test` 700-780 regression rule:
  the named UPSERT conflict target is checked first even when other unique
  constraints also fail.

Dependency closure:

- No new support component is needed. The tests reuse the existing native
  `SQLiteUpsertDoUpdateWherePlan` conflict-arm and RETURNING projection
  behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetFirstTest.php`
- Result: `1 test files, 14041 assertions, 0 failures`.
