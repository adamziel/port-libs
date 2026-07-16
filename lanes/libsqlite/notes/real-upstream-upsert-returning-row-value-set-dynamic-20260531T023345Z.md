# real-upstream-corpus-upsert-returning-dynamic-20260531T023345Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4` 1.7: `DO UPDATE SET (b, c) = (SELECT 'x', 'y')`
  - `upsert4` 1.8: `DO UPDATE SET (c, a) = ('four', 4)`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - RETURNING emits changed row images for INSERT/UPSERT rows.

Implementation:
- Extended `SQLiteUpsertReturningSql` assignment parsing to expand row-value
  `SET (col, ...) = (...)` terms into per-column assignment callbacks.
- Supports literal row-value assignment and the upstream-style
  parenthesized `SELECT` literal tuple used by `upsert4.test`.
- Keeps assignment evaluation atomic against the original current/excluded row
  image already passed to existing callbacks.

Focused test growth:
- Added `SQLiteRealUpstreamUpsertReturningRowValueSetDynamicTest.php`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRowValueSetDynamicTest.php`
- Result: `1 test files, 8014 assertions, 0 failures`.
- Distinct TestRunner PASS cases: 2006.

Non-overlap:
- Existing dynamic UPSERT/RETURNING batches cover omitted conflict targets,
  DO NOTHING suppression, conflict-arm priority, composite target tails, and
  repeated row streams.
- This slice covers row-value `DO UPDATE SET` assignment parsing/evaluation
  and the resulting RETURNING row image.

Dependency closure:
- No new support component needed; this reuses native
  `SQLiteUpsertReturningSql` parsing/evaluation and existing RETURNING
  projection helpers.

Root harness:
- Not run; isolated micro-slice.
