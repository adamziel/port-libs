# real-upstream-corpus-upsert-returning-dynamic-20260530T202206Z-0

Status: ready lane patch for real upstream `RETURNING` corpus growth.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- Ported section families `returning1-1.0` through `returning1-4.5`, plus focused behavior from `returning1-15.0`, `returning1-16.0`, and `returning1-17.1`.

Patch:

- Added `SQLiteRealUpstreamReturning1BroadCorpusTest.php`.
- The test uses generic `app_settings` / `foo` rows and existing native PHP executors:
  - `SQLiteUpsertReturningSql` for INSERT/UPSERT `RETURNING`.
  - `SQLiteUpdateDeleteLimitPlan` for UPDATE/DELETE `RETURNING`.
- Coverage exercises insert `RETURNING` source order, NULL/default-like row images, rowid-style projections, insert-select/xfer-style copied rows, UPDATE post-image rows, DELETE old-image rows, UPSERT mixed insert/update/skip order, duplicate in-statement UPSERT rows returning the first row id, and REAL affinity labels.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturning1BroadCorpusTest.php`
- Result: `1 test files, 1681 assertions, 0 failures`.

Dashboard expectation:

- Count as PASS-line/focused assertion growth: `+1681` focused TestRunner cases.
- `benchmarkDenominator.mapped` is unchanged; this ports behavior from an already hydrated upstream script and does not add a new manifest row.

Non-overlap:

- Avoids the existing upsert5 multi-arm matrix, upsert1/upsert2/upsert3 conflict-target dynamic-yield tests, returning1 sections 20 correlated DELETE returning, trigger/view `RETURNING`, row-value `RETURNING`, WAL/VFS, B-tree, JSON, PRAGMA, and suite-evidence surfaces.
- This slice focuses on broad `returning1.test` baseline INSERT/UPDATE/DELETE/UPSERT row-image behavior and xfer/affinity/duplicate-row returning semantics.

Dependency closure:

- No new support component is needed. The patch reuses existing native PHP UPSERT and UPDATE/DELETE RETURNING executors.
