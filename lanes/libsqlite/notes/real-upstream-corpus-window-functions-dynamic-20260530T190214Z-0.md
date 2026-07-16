# real-upstream-corpus-window-functions-dynamic-20260530T190214Z-0

Base accepted HEAD: 28d061295d83cf4ef005caf2fa1b98587d6f90d3.

Added `SQLiteRealUpstreamWindowPushdownDynamicTest.php`, a real upstream SQLite
window corpus batch based on `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`.

Upstream scenarios ported:

- `windowpushd.test` 1.0-1.4: `row_number()` view partitioned by `grp_id` with
  equality pushdown.
- `windowpushd.test` 2.0-2.1: `v1`, `v2`, and `v3` window views with pushed
  `IN`, equality, less-than, and range filters.
- `windowpushd.test` 2.1.4.1-2.1.4.3: grouped aggregate subquery with a window
  partition keyed by `sum(y)`, plus equality and less-than predicates.

Focused PASS cases added: 1,432 distinct TestRunner cases. This is
non-overlapping with the current accepted window dynamic batch, which already
covers `window3.test`, `window4.test`, `windowE.test`, `windowfault.test`, and
selected `window1.test` frame behavior, but not `windowpushd.test` push-down
view/subquery behavior.

Dependency closure: no new support component needed. The batch reuses existing
bounded native window helpers (`SQLiteWindowFunction`) and array-row oracles.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  was attempted; the guard file is absent in this worktree.
- `git diff --check -- lanes/libsqlite`
