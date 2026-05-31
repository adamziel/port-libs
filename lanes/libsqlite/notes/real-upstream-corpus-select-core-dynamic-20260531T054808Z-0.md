# real-upstream-corpus-select-core-dynamic-20260531T054808Z-0

Blocked as overlap, not handed off as new PASS growth.

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- Scenario `select4-15.1`, the compound `SELECT DISTINCT` self-join regression
  for coroutine/Yield register preservation.

Current-base finding:

- The exact upstream behavior is already present in
  `lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicYieldTest.php`.
- The existing test cites `select4.test` `select4-15.1` and covers 1,250
  dynamic seeds over generic `stream_rows` data.
- The existing lane note is
  `lanes/libsqlite/notes/real-upstream-corpus-select-core-dynamic-yield-20260531.md`.

Reason this slice is not ready:

- A new test file for this same `select4-15.1` corpus section would be duplicate
  PASS-line inflation, not non-overlapping real upstream behavior.
- The hard throughput gate for `real-upstream-corpus-*` slices requires new
  behavior coverage, a large focused assertion batch, or a blocker fix. This
  slice found overlap instead of a missing behavior or runner blocker.

Focused verification run for the already-present coverage:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicYieldTest.php`
  - Result: `1 test files, 5004 assertions, 0 failures`

Next larger batch to try:

- Select a not-yet-covered residual section from the hydrated SELECT corpus,
  preferably one of the excluded `selectA.test` collation/order rows
  (`selectA-2.42`, `selectA-2.43`, `selectA-2.44`, `selectA-2.59`,
  `selectA-2.64`) if table-declared/blob collation propagation is fixed first,
  or a verified non-overlapping `subselect.test`/`e_select2.test` cluster.
- The next ready handoff should cite the precise upstream scenarios and add at
  least 1,000 distinct TestRunner PASS cases or 5,000 behavior assertions, or
  fix the named collation propagation blocker that unlocks that volume.

Dependency closure:

- No new support component was needed for this overlap audit. Future SELECT
  residual work should reuse `SQLiteSelectSql` unless it exposes a concrete
  executor/planner primitive gap.
