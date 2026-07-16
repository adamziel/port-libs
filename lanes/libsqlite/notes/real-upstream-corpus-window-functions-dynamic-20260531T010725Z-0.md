# real-upstream-corpus-window-functions-dynamic-20260531T010725Z-0

Base accepted HEAD: `714d8628d70df34f443545659c4afed0ff8c4b1b`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported behavior cluster: `window1.test` sections `4.1` through `4.10.2`, covering partitioned window aggregate execution, ordered running frames, descending order, `count(*)`, and `group_concat()` separator behavior.

Patch summary:

- Added `SQLiteRealUpstreamWindow1PartitionDynamicTest.php`.
- Adds 1,000 dynamic upstream-backed partition/running aggregate cases plus one source-citation case.
- The cases vary partition column, order column, descending order, row count, row values, and separator while checking `sum`, `avg`, `count`, and `group_concat` over `ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`.
- Expected values are computed by an independent test-local oracle and compared against `SQLiteWindowFunction::aggregateFrameBetweenValues()`.

Non-overlap:

- This does not repeat the already accepted window following-frame, `window4`, `window5`, `window9`, JSON window, named-window, or value-offset dynamic slices.
- This slice specifically targets `window1.test` partitioned/running aggregate behavior and expands dynamic coverage without adding metadata-only runner rows.

Verification:

- Red-first note: the initial focused run failed on strict `avg()` type expectations (`30` vs `30.0`), then the oracle was corrected to match SQLite floating-point `avg()` results.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1PartitionDynamicTest.php`: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1PartitionDynamicTest.php`: `1 test files, 37985 assertions, 0 failures`, 1001 PASS lines.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteWindowFunction` aggregate frame implementation and the existing lane TestRunner.
