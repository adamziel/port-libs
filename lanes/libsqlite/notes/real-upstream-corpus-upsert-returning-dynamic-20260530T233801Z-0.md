# real-upstream-corpus-upsert-returning-dynamic-20260530T233801Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoinc.test`
  - `autoinc-11.1`: AUTOINCREMENT sequence state must not be corrupted by UPSERT source rows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - RETURNING stream semantics: changed inserted/updated rows are yielded in statement order.

Implementation:

- Added `SQLiteRealUpstreamUpsertReturningAutoincrementDynamicTest.php`.
- The test builds 256 deterministic explicit-rowid UPSERT streams, executes a PDO SQLite oracle for each, and compares the native `SQLiteUpsertDoUpdateWherePlan` result plus `SQLiteAutoincrementState` sequence record.
- Each stream asserts final table image, RETURNING stream, `changes()`, highest explicit source rowid sequence state, and sequence row lifecycle.

Focused count:

- 1026 focused TestRunner PASS cases in the new file.
- 256 dynamic oracle-backed source streams; each stream contributes 4 distinct focused PASS cases.

Non-overlap:

- This does not repeat the accepted `upsert2` WHERE/yield matrix, `upsert3` literal `excluded` table behavior, `upsert5` conflict-arm ordering, broad `returning1-17` duplicate-rowid stream, correlated RETURNING subqueries, trigger/FK RETURNING, or parser-level SELECT input UPSERT coverage.
- This slice owns the real upstream `autoinc-11.1` interaction between explicit-rowid UPSERT source rows, AUTOINCREMENT sequence state, and RETURNING stream output.

Dependency closure:

- No new support component is required. The slice reuses the existing native PHP UPSERT executor and AUTOINCREMENT sequence state model.
