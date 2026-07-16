# Real Upstream Corpus UPSERT/RETURNING Dynamic Composite Slice

Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`.

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260530T195424Z-0`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert3.test`
  - `upsert3-130`
  - `upsert3-140`
  - `upsert3-200`
  - `upsert3-210`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - `upsert4-1.x.1` through `upsert4-1.x.8`
  - `upsert4-2.x` target-analysis error cases
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-1.0`, `1.2`, `1.4`, `2.1`, `3.1`, `4.5`, `6.0`, `7.2` through `7.8`, and `8.4`

Lane changes:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCompositeTest.php`.
- Updated `lanes/libsqlite/lane-status.json` for the lane-local PASS delta.

Focused behavior covered:

- Composite unique UPSERT conflict targets and reversed target order.
- Repeated same-statement composite conflicts using the current row image.
- `excluded` table-name collision behavior modeled as incoming-row value access.
- Target-analysis cases for primary-key conflicts, secondary unique conflicts, row-value assignments, and primary-key movement.
- RETURNING projection shape for explicit columns, `rowid`, `*`, `rowid, *`, literals, and invalid qualified projections.
- Two-statement dynamic sequences that verify changed rows, skipped rows, matched arms, and RETURNING streams remain coherent across statement boundaries.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicCompositeTest.php`
  - `1 test files, 1633 assertions, 0 failures`
  - `1267` PASS lines

Non-overlap:

- This slice avoids the already accepted UPSERT/RETURNING broad, tail, priority, schema-variant, statement, target-analysis, redundant-conflict, and correlated-delete batches by focusing on composite conflict targets from `upsert3.test`, row-value/secondary-conflict target analysis from `upsert4.test`, and RETURNING projection/error shape from `returning1.test`.

Dependency closure:

- No new support component needed. The slice reuses the native PHP row-array UPSERT/RETURNING helper and the repo TestRunner.
