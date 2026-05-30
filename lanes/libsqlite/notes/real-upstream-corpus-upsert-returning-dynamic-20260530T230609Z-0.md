# real-upstream-corpus-upsert-returning-dynamic-20260530T230609Z-0

Source truth: hydrated upstream SQLite files
`test/upsert1.test`, `test/upsert2.test`, and `test/returning1.test`.

Ported scenario groups:

- `upsert1-700` through `upsert1-780`: explicit UPSERT conflict target priority
  when several unique constraints conflict.
- `upsert2-200` and `upsert2-201`: repeated INSERT SELECT source rows update
  against the current statement image.
- `upsert2-320` and `upsert2-420`: DO UPDATE with false WHERE produces no
  returning row and leaves the current row unchanged.
- `returning1-4.5`: mixed inserted and updated rows preserve statement
  RETURNING order.
- `upsert1-320`: partial unique index predicate gates conflict matching.
- `upsert1-100` through `upsert1-102`: DO NOTHING skips primary-key and
  alternate unique conflicts while returning only inserted rows.

Focused coverage: `SQLiteUpstreamUpsertReturningDynamicRealCorpusTest.php`
adds 1,560 distinct TestRunner cases from dynamic generic application rows.
The batch is non-overlapping with the accepted excluded-alias RETURNING and
generic upsert5/target-analysis/tail batches because it focuses on target
priority, repeated current-row statement images, false-WHERE no-return rows,
partial-index conflict matching, mixed RETURNING order, and DO NOTHING
multi-constraint skips.

Dependency closure: no new support component is needed; this reuses native PHP
UPSERT dynamic execution and RETURNING projection helpers already present in
`lanes/libsqlite/src`.
