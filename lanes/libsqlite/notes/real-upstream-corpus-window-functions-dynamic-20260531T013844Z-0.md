# real-upstream-corpus-window-functions-dynamic-20260531T013844Z-0

Slice: `real-upstream-corpus-window-functions-dynamic-20260531T013844Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`

Ported behavior cluster:

- `windowfault.test` faultsim sections `1` through `8`, covering ranking, distribution, value, min/max, aggregate, oversized `ntile()`, no-ORDER distribution, and unused named-window result parity under recoverable OOM simulation.
- Dynamic recoverable-fault variants over the same result contracts, including filtered aggregate frame parity, retry-step markers, large bucket `ntile()` cardinality, and no-order peer distribution.

Implementation delta:

- Expanded focused PHP TestRunner coverage in `SQLiteRealUpstreamWindowFaultDynamicTest.php`.
- No production code was changed. The tests exercise existing native `SQLiteWindowFunction` and `SQLiteVdbeWindowAggregateCursor` behavior against independent expected vectors and dynamic upstream-inspired recoverable fault cases.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowFaultDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFaultDynamicTest.php`: `1 test files, 7009 assertions, 0 failures` with `1409` focused PASS lines.
- `git diff --check -- lanes/libsqlite`: passed.
- `SQLiteNoWordPressSpecificApiTest.php` is not present in this accepted worktree, so the API guard was not run.

Non-overlap:

- This owns upstream `windowfault.test` result/fault parity. It does not repeat accepted `window1` sales/view/partition coverage, `window2` frame-boundary rows, `window3/window4` ranking/value batches, `window5` custom aggregate behavior, `window6` value-function argument validation, `window7/window8` GROUPS/RANGE matrices, `window9`, `windowA` through `windowE`, `windowerr`, `windowpushd`, JSON, WAL, VFS, B-tree, PRAGMA, trigger, or suite metadata rows.

Dependency closure:

- No new support component is needed; the slice reuses existing native PHP window ranking, frame, aggregate, and cursor primitives.
