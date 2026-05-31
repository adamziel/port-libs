# real-upstream-corpus-window-functions-dynamic-20260531T094716Z-0

- Base accepted HEAD: `ffcc95ebfcac7bbcd16b24facd07c90559f1565a`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `42.3`, `42.4`, `42.6`, and `42.7`.
- Behavior ported: implicit aggregate output rows now retain the first source row's bare columns before window expressions run, matching grouped aggregate rows. This lets parser-level `SELECT` execute aggregate/window combinations such as `sum(a), max(b) OVER ()` and `sum(sum(b)) OVER (ORDER BY a)` from the upstream corpus.
- Red-first evidence: before the source change, `SQLiteSelectSql::execute("SELECT sum(a) AS sum_a, max(b) OVER () AS max_b FROM t1", ...)` failed with `InvalidArgumentException: SQLite SELECT expression row is missing column b`.
- Patch content: `SQLiteGroupedAggregate::summarizeAll()` mirrors `summarize()` by copying first-row columns that are not already aggregate summary keys, then `SQLiteRealUpstreamWindow1AggregateRowsDynamic20260531Test.php` exercises the fixed parser/executor path.
- Focused growth: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggregateRowsDynamic20260531Test.php` passed with `1 test files, 6006 assertions, 0 failures`; this is 1006 distinct focused PASS cases.
- Non-overlap: covers `window1.test` aggregate/window sections `42.3-42.7`; avoids existing window1 sections for lead/rank/regional sales/view/trigger/subquery partitions and avoids window2/window3/window4/window6/window7/window8/window9/windowA-E/filter/pushdown coverage already present in this worktree.
- Dependency closure: no new support component is needed; the patch reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteGroupedAggregate`, and `SQLiteWindowFunction` behavior.
- Root harness: not run - isolated micro-slice.
