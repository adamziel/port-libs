# real-upstream-corpus-upsert-returning-dynamic-20260531T055809Z-0

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T055809Z-0`

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Added focused PHP corpus file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicSelectMatrixTest.php`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test`
  - `upsert2-200`: `WITH nx(...) AS (VALUES...) INSERT INTO t1(a,b) SELECT ... ON CONFLICT(a) DO UPDATE ...`
  - `upsert2-201`: `INSERT INTO main.t1 AS t2(...)` with update expressions resolved through the target alias.
  - `upsert2-202`: original table qualifier hidden after target alias.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-4.5`: mixed INSERT/UPSERT UPDATE statement preserves `RETURNING` row order.

Coverage added:

- 1000 deterministic upstream-backed dynamic SELECT-input UPSERT cases.
- Each generated case validates final table image, `RETURNING` stream order, changed count, inserted/updated/skipped partitioning, target-alias resolution, and omitted `DEFAULT` column completion.
- Two explicit edge checks cover alias-hidden original table qualifier rejection and unqualified target-name parity.
- The focused file adds 1004 TestRunner PASS lines and 6004 assertions.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicSelectMatrixTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicSelectMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicSelectMatrixTest.php`
  - `1 test files, 6004 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicAliasDefaultTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusUpsertReturningDynamicSelectMatrixTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicTest.php lanes/libsqlite/tests/SQLiteUpstreamUpsertReturningDynamicRealCorpusTest.php`
  - `4 test files, 10613 assertions, 0 failures`
- Generic no-domain API guard
  - not run; guard file is absent in this worktree.
- `git diff --check -- lanes/libsqlite`
  - passed.

Dependency closure:

- No new support component is needed. This reuses `SQLiteUpsertReturningSql` SELECT-input UPSERT execution and `SQLiteUpsertDoUpdateWherePlan` row-array conflict application.

Non-overlap:

- This slice does not repeat accepted UPSERT catch-all, excluded-alias, target-admission, scope-matrix, composite-tail, trigger/FK, or RETURNING temp-trigger clusters. It owns the remaining high-yield dynamic matrix around `upsert2.test` SELECT input plus target alias/default-column completion and `returning1.test` mixed INSERT/UPDATE RETURNING order.
