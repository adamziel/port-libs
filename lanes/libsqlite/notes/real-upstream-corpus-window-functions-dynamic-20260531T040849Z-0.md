# real-upstream-corpus-window-functions-dynamic-20260531T040849Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`.
- Ported sections:
  - `windowpushd.test` `1.0` through `1.4`: `row_number() OVER (PARTITION BY grp_id)` view behavior and partition-key predicate pushdown.
  - `windowpushd.test` `2.0` through `2.1.5`: `max(c) OVER (PARTITION BY a)` view behavior and partition-key predicate pushdown.
  - `windowpushd.test` `2.3.1` through `2.3.6`: non-partition predicates over a windowed view must filter after the window frame is computed.
  - `windowpushd.test` `2.4.1` through `2.4.3`: grouped aggregate rows feeding a partitioned window aggregate, with outer predicates applied after grouped/window results.
- Focused coverage: `SQLiteWindowPushdownDynamicCorpusTest.php` adds 1,000 distinct TestRunner PASS cases and 4,748 behavior assertions from dynamic variants of the upstream pushdown scenarios.
- Implementation delta: no new production helper was required; the batch exercises existing native `SQLiteWindowFunction` row-number and aggregate frame behavior as the window executor primitive behind view/subquery pushdown decisions.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowPushdownDynamicCorpusTest.php` -> `1 test files, 4748 assertions, 0 failures`.

Dependency closure: no new support component is needed. This reuses the existing native PHP window frame executor and focused TestRunner infrastructure; no ext/sqlite, Tcl runner, or shared upstream cache mutation is required.

Non-overlap: this slice covers `windowpushd.test` pushdown-safe and pushdown-unsafe windowed view/subquery behavior. It does not repeat accepted `window2` ROWS generated boundary behavior, `window3/window4` frame aggregate/value coverage, `window5` custom inverse functions, `window6` value-function default frames, `window7/window8` RANGE/GROUPS matrices, `window9` NOCASE ranking, `windowE`, JSON/WAL/B-tree/VFS behavior, or metadata-only upstream runner rows.
