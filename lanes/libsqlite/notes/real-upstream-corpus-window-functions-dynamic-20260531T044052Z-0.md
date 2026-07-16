# real-upstream-corpus-window-functions-dynamic-20260531T044052Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`

Ported scenarios:

- `windowE.test` `1.2`: text `ORDER BY` with a custom collation and `RANGE BETWEEN 1 PRECEDING AND 2 PRECEDING` keeps the current peer for nonnumeric RANGE offsets.
- `windowE.test` `3.1`: numeric `RANGE 366.0 PRECEDING` propagates the first non-zero `max(c2)` value to later rows.
- `windowE.test` `4.1`: `total(b)` over `ROWS BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING` preserves large integer float totals.
- `windowE.test` `4.2`: `total(b)` over `ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING` preserves the same large integer frame behavior.
- `windowE.test` `5.1`: `sum(id)` over `ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING` advances row frames correctly.

Implementation delta:

- Added `SQLiteRealUpstreamWindowEDynamicCorpusTest.php`.
- No production source change was needed; the existing native `SQLiteWindowFunction::aggregateFrameBetweenValues()` implementation already handles these upstream frame semantics.

Focused count:

- 1,250 distinct TestRunner PASS cases.
- 21,750 focused behavior assertions.

Non-overlap:

- This owns `windowE.test` text/numeric RANGE and large-number ROWS aggregate-frame coverage only.
- It avoids accepted `window1` lead/LIMIT, `window2` ROWS, `window3/window4/window6` dynamic value/ranking coverage, `window7` GROUPS/RANGE matrix coverage, `windowpushd` pushdown coverage, window error/fault corpus, JSON/WAL/VFS/B-tree/PRAGMA/planner clusters, and metadata-only upstream runner rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicCorpusTest.php`
  - Result: `1 test files, 21750 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses the existing native PHP window aggregate frame helper and focused TestRunner infrastructure; no ext/sqlite, Tcl runner, live service, or new dependency activation is required.
