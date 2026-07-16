# Real Upstream Window Dynamic Corpus

Session: `port-dev-sqlite-yield-dyn-real-window-20260530T230118Z`
Base accepted HEAD: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`

## Upstream Sources

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`
  - `1.1` custom `win()` sorted frame state and `median()` value.
  - `2.0` and `2.1` custom `sumint()` running and bounded `ROWS` frames.
  - Dynamic variants extend the same custom inverse window-state behavior across bounded `ROWS` frames with varying row counts and values.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
  - `8.1`, `8.2`, and `9.0` partitioned ranking, bounded running sums, and recursive `group_concat()` windows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window9.test`
  - `1.2` and `1.3` NOCASE peer dense-rank behavior.
  - Dynamic variants extend the same NOCASE peer and partition rank behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test`
  - Negative frame offset and unsupported custom/window frame guard behavior.

## Evidence

- Added `lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusNextTest.php`.
- Focused result:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicRealCorpusNextTest.php`
  - `1 test files, 25179 assertions, 0 failures`
  - `1258` distinct TestRunner PASS cases.
- Expected selected movement: `1040058 -> 1041316` PHP PASS lines if accepted.
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`.

## Non-Overlap

This batch avoids accepted window4 navigation, window7/window8 dynamic frame matrices, window9 fixed corpus, windowA/windowB ordered RANGE NULL placement, windowC/windowD group-concat and expression behavior, windowpushd planner pushdown, and prior row-value/window UPSERT coverage. It focuses on custom inverse window functions, keyword/named-window sample frames, NOCASE dense-rank peers, and dynamic invalid-frame guards.

## Dependency Closure

No new support component is needed. The test reuses the existing native `SQLiteWindowFunction` helpers and the existing PHP test harness.
