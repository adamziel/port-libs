# real-upstream-corpus-upsert-returning-dynamic-20260531T041917Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-18.0` through `returning1-18.1`: `INSERT INTO view_2 DEFAULT VALUES RETURNING *` must report `no such collation sequence: TRUE` before any trigger body or RETURNING row is yielded.
  - `returning1-19.0` through `returning1-19.1`: `CREATE TRIGGER IF NOT EXISTS` against an existing trigger skips the duplicate body, so embedded `INSERT ... RETURNING FALSE/TRUE` statements do not produce an error.

Implementation:

- Added `SQLiteReturningTriggerDdlPlan` for the bounded RETURNING trigger-DDL error-order behavior.
- Added `SQLiteRealUpstreamCorpusUpsertReturningDynamicTriggerDdlErrorTest.php` with 1000 deterministic dynamic variants and 4000 focused PASS cases over those real upstream sections.

Non-overlap:

- This avoids accepted correlated DELETE RETURNING, recursive trigger returning visibility, writable-schema returning, virtual-table returning, UPSERT arm ordering, `upsert4` excluded-alias SQL, and `returningfault` fault-cleanup batches.

Dependency closure:

- No new support component is needed. The slice reuses bounded native PHP trigger/view metadata modeling and adds only the missing RETURNING error-order plan.
