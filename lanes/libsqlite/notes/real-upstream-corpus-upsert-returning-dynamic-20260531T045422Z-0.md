# real-upstream-corpus-upsert-returning-dynamic-20260531T045422Z-0

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-18.1`: `INSERT INTO view_2 DEFAULT VALUES RETURNING *` reports `no such collation sequence: TRUE` before trigger body effects.
  - `returning1-19.1`: duplicate `CREATE TRIGGER IF NOT EXISTS` does not validate the skipped trigger body even when that body contains `RETURNING` statements.

Implementation:

- Added `SQLiteReturningDdlErrorPlan` as a generic native helper for RETURNING DDL/error-order evidence.
- Added `SQLiteRealUpstreamReturningDdlErrorDynamicTest.php` with 1000 deterministic generic variants and 6000 focused TestRunner PASS cases.

Non-overlap:

- Existing UPSERT/RETURNING dynamic files already cover `upsert1` through `upsert5`, `upsertfault`, `returningfault`, `returning1-1.0` through `17`, `20`, and `21` through `24`, plus trigger row streams and correlated subqueries.
- This slice owns `returning1-18.1` and `returning1-19.1` only.

Dependency closure:

- No new support component is needed. The slice reuses native RETURNING trigger/error-order planning and generic DDL short-circuit evidence.
