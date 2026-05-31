# real-upstream-corpus-window-functions-dynamic-20260531T061446Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `10.1-10.8`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `18.3.1-18.3.5`

Ported behavior:
- Dynamic regional-sales `row_number()` partition ranking and top-two filtering from `window1.test 10.1`.
- Partitioned running and following sums from `window1.test 10.2-10.6`.
- Full-frame scalar subquery composition and FILTER excluding the outer row from `window1.test 10.7-10.8`.
- Chained window definitions that inherit `PARTITION BY` and add `ORDER BY` for `group_concat` from `window1.test 18.3.1-18.3.5`.

Non-overlap:
- Avoids accepted `window8` groups, `window4` navigation, `window9` aggregate-subquery/collation, `windowE` total/sum, JSON table windows, and the recent window-pushdown batches.
- Adds one new focused PHP file with 1,002 distinct TestRunner cases. The 1,000 dynamic cases are real upstream `window1.test` behavior variants, not metadata admission rows.

Dependency closure:
- No new support component needed. The batch reuses native `SQLiteWindowFunction` row-number, aggregate-frame, FILTER, full-frame, and chained-window behavior.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SalesChainDynamic20260531Test.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SalesChainDynamic20260531Test.php` passed: `1 test files, 21005 assertions, 0 failures`, with `1002` distinct PASS lines.
