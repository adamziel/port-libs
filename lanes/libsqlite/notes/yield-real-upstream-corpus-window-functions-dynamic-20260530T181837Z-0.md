# Real Upstream Window Functions Dynamic Corpus

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260530T181837Z-0`

Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
  - scenarios `3.5` and `3.6`: previous-only and following-only ROWS frames.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test`
  - scenarios `1.2`, `4.1`, `4.2`, and `5.1`: inverted RANGE frames, current-row to unbounded/two-following totals, and filtered following sums.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowfault.test`
  - scenario `13.1`: numeric centered RANGE frame behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
  - EXCLUDE/GROUPS frame behavior from the upstream window corpus.

## Handoff

Added `SQLiteRealUpstreamWindowFunctionsDynamicCorpusTest.php`, a source-neutral application-row corpus batch against `SQLiteVdbeWindowAggregateCursor`.

The test ports distinct upstream window behavior into PHP TestRunner cases:

- ROWS previous/following bounded frames.
- RANGE inverted and centered numeric frames.
- GROUPS frames with `EXCLUDE GROUP` and `EXCLUDE TIES`.
- FILTER-aware numeric aggregates.
- unfiltered `first_value`, `last_value`, and `nth_value` value-function semantics over the same frames.

Focused count: `4321` assertions / selected PASS lines from the new file.

Expected dashboard movement after acceptance: `phpPass +4321`; mapped denominator unchanged.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicCorpusTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicCorpusTest.php`
  - `1 test files, 4321 assertions, 0 failures`

Root harness: not run - isolated micro-slice.

Dependency closure: no new support component needed; this reuses the existing native `SQLiteVdbeWindowAggregateCursor`, numeric aggregate, text aggregate, and sort comparison helpers.

Non-overlap: avoids the accepted smaller window dynamic frames batches by adding a new high-yield real upstream corpus file with different upstream scenario ranges and dynamic row coverage. It does not add WordPress-specific APIs, examples, or metadata-only admission rows.
