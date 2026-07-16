# Real Upstream Window7 RANGE/GROUPS Dynamic Corpus

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T062458Z-0`
- Base accepted HEAD: `68a3731675769814ce7d56857d9182ac7f8b3613`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window7.test`
- Ported sections: `window7.test` `1.2` through `1.8.1`, covering peer-group `sum(b)` window frames over generated `t3(a,b)` rows:
  - `GROUPS BETWEEN CURRENT ROW AND CURRENT ROW`
  - `GROUPS BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `GROUPS BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `RANGE BETWEEN 0 PRECEDING AND 0 FOLLOWING`
  - `RANGE BETWEEN 2 PRECEDING AND 2 FOLLOWING`
  - `RANGE BETWEEN 2 PRECEDING AND 1 FOLLOWING`
  - `RANGE BETWEEN 0 PRECEDING AND 1 FOLLOWING`
- Added focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamWindow7RangeGroupsDynamicNextTest.php`
- Focused growth: `1001` distinct TestRunner PASS cases and `3004` behavior assertions.
- Non-overlap: this owns `window7.test` section-1 RANGE/GROUPS peer-sum frames. It avoids accepted `window1` range-offset and lead/limit rows, `window2` ROWS matrix, `window4` value-function partitions, `window6` value/default-frame behavior, `window8` GROUPS coverage, `window9` collation/ranking rows, `windowA` NULL placement RANGE rows, `windowB` inverse JSON rows, `windowC` separator rows, `windowD` truth semantics, `windowE` collation RANGE rows, `windowerr`, `windowfault`, `windowpushd`, and all suite metadata rows.
- Dependency closure: no new support component is needed; the slice reuses native `SQLiteWindowFunction::aggregateFrameBetweenValues()` RANGE/GROUPS execution.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow7RangeGroupsDynamicNextTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow7RangeGroupsDynamicNextTest.php`
- `git diff --check -- lanes/libsqlite`
