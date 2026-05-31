# real-upstream-corpus-window-functions-dynamic-20260531T011234Z-0

Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`.

Implemented one real upstream window-function behavior batch from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test`:

- `window3.test` 1.0 generated `t2(a INTEGER PRIMARY KEY, b INTEGER)` corpus.
- `window3.test` 1.1, 1.1.2.1, and 1.1.2.2 running `max()` and `min()` RANGE frames.
- `window3.test` 1.1.3.1-1.1.3.3 `row_number()` behavior, including partition reset over `b%10`.
- `window3.test` 1.1.4.1-1.1.4.6 `dense_rank()` behavior over primary-key, peer, modulo, and partitioned peer orderings.

Focused behavior/assertion delta:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow3DynamicRankRangeBatchTest.php`.
- Focused run: `1 test files / 1529 assertions / 0 failures`.
- Countable selected movement: `+1529` focused TestRunner PASS lines.
- Lane-local selected total: `1436524 -> 1438053 pass / 0 fail`.
- Mapped denominator movement: none; coverage remains `1589 / 1589`.

Non-overlap:

This slice avoids the accepted `window2` large corpus, `window7` GROUPS/RANGE,
`window9` collation/filter, window pushdown, JSON table window, SELECT SQL
window source, and root-gate no-order window guard surfaces. It uses the
distinct upstream `window3.test` generated ranking/range corpus.

Dependency closure:

No new support component is needed. The batch reuses existing native PHP
`SQLiteWindowFunction` ranking and aggregate frame helpers plus lane-local
TestRunner infrastructure.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow3DynamicRankRangeBatchTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow3DynamicRankRangeBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

`SQLiteNoWordPressSpecificApiTest.php` is not present in this checkout; the
available generic no-domain-specific API guard passed instead.

Root harness: not run - isolated micro-slice.
