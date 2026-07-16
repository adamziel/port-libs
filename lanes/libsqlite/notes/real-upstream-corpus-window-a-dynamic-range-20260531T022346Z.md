# Real upstream windowA/windowE dynamic range corpus

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T022346Z-0`
- Base accepted HEAD: `5237a0589958b13a7df177706c832014179deb3d`
- Added focused PHP corpus: `lanes/libsqlite/tests/SQLiteRealUpstreamWindowADynamicRangeTest.php`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
- Ported upstream scenarios:
  - `windowA.test` `1.1-1.6`, `2.1-2.6`, `3.1-3.4`, `4.0`: descending `RANGE` frames with `NULLS FIRST`/`NULLS LAST`, bounded/unbounded/current/preceding frame edges, and `group_concat()` frame output.
  - `windowE.test` `3.1`, `4.1`, `4.2`, `5.1`, `5.2`: large numeric `RANGE` frame max propagation, total/sum overflow-adjacent window outputs, and mixed integer/real frame accumulation.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowADynamicRangeTest.php` passed with `1 test files, 1192 assertions, 0 failures`.
- Non-overlap: this does not repeat the accepted mixed-type REAL RANGE, windowB/windowD/window12/windowfault, JSON-object inverse, or grouped SELECT text batches. It focuses on `windowA.test` NULL placement over descending numeric RANGE frames and `windowE.test` large numeric frame accumulation.
- Dependency closure: no new support component is needed; this reuses the existing native `SQLiteWindowFunction` bounded window-frame helpers.
- Exclusion: `windowE.test` `1.2`/`1.3` custom collation RANGE behavior was not included because the current helper does not model custom text collation RANGE frames; that should be a separate behavior slice if needed.
