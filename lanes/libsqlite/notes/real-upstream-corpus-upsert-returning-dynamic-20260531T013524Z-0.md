# real-upstream-corpus-upsert-returning-dynamic-20260531T013524Z-0

Source truth: hydrated upstream SQLite files
`test/returning1.test` and `test/upsert5.test`.

Ported scenario groups:

- `returning1.test 20.1` through `20.3`: RETURNING subqueries that reference
  the modified table must be correlated and recomputed after each row change.
- `upsert5.test 1.400` through `1.505`: catch-all UPSERT arms and targeted
  DO NOTHING arms decide whether a row yields RETURNING output.
- `returning1.test 4.1` through `4.5`: RETURNING emits only changed insert or
  update rows, not DO NOTHING rows.

Focused coverage: `SQLiteRealUpstreamCorpusUpsertReturningStatementCurrentDynamicTest.php`
adds 1,003 distinct TestRunner PASS cases over 1,000 varied generic
application UPSERT streams plus source/dependency evidence. Each stream compares
a full-statement yield trace with a single-step statement-current oracle so the
RETURNING-side min/max/sum/count view is recomputed after each insert or update
instead of being reused across the statement.

Non-overlap: current accepted UPSERT/RETURNING batches already cover target
priority, omitted target DO NOTHING, composite targets, excluded/table aliases,
projection shape, long row streams, and yield trace boundaries. This batch owns
statement-current recomputation for RETURNING aggregate/subquery-style values
across UPSERT insert/update/skip mixtures.

Dependency closure: no new support component is needed; this reuses native PHP
UPSERT conflict-arm execution and RETURNING yield trace behavior already present
under `lanes/libsqlite/src`.
