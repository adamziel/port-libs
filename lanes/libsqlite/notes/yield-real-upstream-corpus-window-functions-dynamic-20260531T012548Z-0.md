# real-upstream-corpus-window-functions-dynamic-20260531T012548Z-0

Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`

Added `SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php`, a real upstream
`windowE.test` dynamic matrix for numeric `RANGE` and `ROWS` window frames.

Upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:3.1`
  numeric `RANGE 366.0 PRECEDING` max carry behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:4.1-4.2`
  `total()` over `CURRENT ROW` to following frames at integer overflow
  boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test:5.1-5.2`
  `sum()` over `ROWS CURRENT ROW AND 2 FOLLOWING` for integer and mixed
  integer/real accumulators.

Focused movement:

- New focused PASS cases: `1082`
- Focused file assertions after preserving existing cases: `88561`
- Newly added dynamic-case assertions: `87440`
- Expected selected `phpPass` movement: `1493978 -> 1495060`
- Mapped denominator coverage: unchanged, already `1589 / 1589`

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowEDynamicRangeCorpusTest.php`
  passed: `1 test files, 88561 assertions, 0 failures`.

Non-overlap:

This extends the already accepted/static `windowE` rows with 1080 dynamic
behavior cases across generated key/value shapes, aggregate functions, value
functions, numeric range offsets, following frames, and overflow-boundary
accumulator values. It does not repeat accepted `window3` rank/range,
`windowC` separator, `window8` frame matrix, `window9` nocase rank, or static
`windowE` one-off assertions.

Dependency closure:

No new support component is needed. The slice reuses lane-local
`SQLiteWindowFunction` RANGE/ROWS frame, aggregate, value-function, numeric
comparison, and mixed integer/real accumulator helpers.
