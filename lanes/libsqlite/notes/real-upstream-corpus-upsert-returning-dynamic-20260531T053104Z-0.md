# real-upstream-corpus-upsert-returning-dynamic-20260531T053104Z-0

Ported a non-overlapping real upstream UPSERT/RETURNING target-analysis
cluster from the hydrated SQLite upstream checkout.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  scenario `8.5`: unresolved names in the `ON CONFLICT (...) WHERE ...`
  target-analysis predicate are rejected before the UPSERT update body runs.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  scenarios `4.1` through `4.5`: `RETURNING` rows are statement-local and only
  emitted after a row change actually executes.

Focused PHP coverage:

- Added `SQLiteRealUpstreamUpsertReturningTargetWhereDynamicTest.php`.
- 1,000 dynamic `INSERT ... ON CONFLICT(x, [a b]) WHERE
  missing_predicate_N DO UPDATE ... RETURNING ...` cases over generic
  `excluded` table fixtures.
- 1 source-coverage assertion and 1 dependency-closure assertion.
- Total focused assertion count: 1,002.

Non-overlap:

- Avoids the accepted omitted-target `DO NOTHING RETURNING` batch in
  `SQLiteRealUpstreamUpsertReturningDoNothingOmittedTargetDynamicTest.php`.
- Avoids accepted `upsert4.test` excluded-alias update execution in
  `SQLiteUpstreamUpsertReturningExcludedAliasDynamicTest.php`.
- Avoids accepted `upsert5.test` conflict-arm yield priority in
  `SQLiteUpstreamUpsert5ReturningYieldRealCorpusTest.php`.
- This batch owns pre-execution conflict-target `WHERE` name-resolution failure
  before any `RETURNING` rows can be produced.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningTargetWhereDynamicTest.php`
  - `1 test files, 1002 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses `SQLiteUpsertReturningSql`
  conflict-target `WHERE` name resolution and the existing `RETURNING` parse
  gate.
