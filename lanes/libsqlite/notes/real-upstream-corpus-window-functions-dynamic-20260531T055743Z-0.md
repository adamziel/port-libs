# real upstream corpus window functions dynamic 20260531T055743Z 0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  section `22.*`.

Behavior covered:

- Ports valid non-negative `RANGE` frame offset handling from upstream
  `window1.test` section 22.
- Covers integer, real, and normalized numeric-text offsets including the
  upstream valid `4.5`, `0.0`, `0.1`, `2.0`, and `1.2` cases.
- Adds 640 generated row-level range-sum cases over rotated application rows
  and 360 generated fractional range-count cases.
- Focused local evidence: `SQLiteRealUpstreamWindow1RangeOffsetDynamicTest.php`
  passes with 1,002 TestRunner PASS cases and 8,963 assertions.

Non-overlap:

- This batch does not repeat the existing `windowerr.test` invalid-offset
  corpus, `window1.test` 10.7/10.8 correlated `FILTER` corpus,
  `windowE.test` real-range overflow coverage, `window7.test` `GROUPS`/`RANGE`
  batches, or custom-collation `RANGE` tests.
- Mapped denominator coverage is already complete at `1589 / 1589`; this is
  PASS-line and behavior-assertion growth only.

Dependency closure:

- No new support component is needed. The batch reuses the native PHP
  `SQLiteWindowFunction` `RANGE` frame evaluator.
