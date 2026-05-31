# Real Upstream Corpus Window5 Custom Dynamic

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T045747Z-0`
- Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test`
    - `window5-1.1`: custom `win()` sorted context and custom `median()`
      cumulative window values.
    - `window5-2.0` and `window5-2.1`: custom `sumint()` running and
      one-preceding/one-following sliding frames.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test`
    - `windowerr-1.1` through `1.6`: negative ROWS/RANGE/GROUPS frame
      offsets are rejected.
    - `windowerr-3.0` and `3.2`: non-integer frame boundary values are
      rejected.
- Ported behavior: lane-local generic PHP assertions exercise
  `SQLiteWindowFunction::sortedFrameTextValues()`,
  `medianFrameBetweenValues()`, `aggregateFrameBetweenValues()`,
  `aggregateFrameValues()`, and `valueFrameValues()` using the exact
  upstream `window5.test` seed rows plus 24 deterministic dynamic rowsets.
- Non-overlap: this slice does not touch accepted `windowE`, pushdown,
  `window4`, `window12`, JSON-object inverse, group-concat separator, or
  mixed-type REAL window clusters. It targets the custom window-function API
  behavior from `window5.test` and frame error guards from `windowerr.test`.
- Focused evidence:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomDynamicCorpusTest.php`
    - `No syntax errors detected`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow5CustomDynamicCorpusTest.php`
    - `1 test files, 996 assertions, 0 failures`
- Expected dashboard movement: `+996` focused PASS lines if accepted, moving
  `phpPass` from `2168479` to `2169475`; mapped denominator remains complete
  at `1589 / 1589`.
- Dependency closure: no new support component is needed. Existing generic
  `SQLiteWindowFunction` frame, custom callback, aggregate, and error-guard
  helpers cover the upstream behavior.
- Domain-specific API note: no new domain-specific libsqlite APIs, fixtures,
  examples, or source names were added.
- Root harness: not run from this isolated micro-slice.
