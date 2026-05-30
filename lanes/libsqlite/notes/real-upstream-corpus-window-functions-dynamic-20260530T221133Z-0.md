# real-upstream-corpus-window-functions-dynamic-20260530T221133Z-0

Added a high-yield real upstream window-function corpus batch based on:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
- Upstream scenarios:
  - `windowpushd.test` `1.0-1.4`: `row_number()` view over indexed `grp_id` with equality predicate pushdown.
  - `windowpushd.test` `2.0-2.1.3.6`: partitioned window max views with pushed equality/range predicates.
  - `windowpushd.test` `2.1.4.1-2.1.4.3`: grouped aggregate subquery with a window partition by grouped `sum(y)`.

The new focused test file is `SQLiteRealUpstreamWindowPushdownLargeDynamicTest.php`.
It contributes 1,009 TestRunner PASS cases: 1,008 generated dynamic behavior cases
plus one source-citation case. The generated cases vary row distributions,
partition keys, score ranges, grouped sums, and grouped max values while executing
native PHP window behavior through `SQLiteWindowFunction` helpers.

Non-overlap: this expands the accepted smaller `windowpushd.test` batch with a
large dynamic behavior window over the same upstream push-down family. It does not
repeat `window1` through `windowE` frame/value/collation matrices, window error
guards, JSON window coverage, SQL text grouped/window coverage, B-tree, WAL, VFS,
PRAGMA, trigger/FK, source-neutral cleanup, or runner metadata rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownLargeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownLargeDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing
native PHP window helper and TestRunner infrastructure; no Tcl runner, ext/sqlite,
or external service is required.
