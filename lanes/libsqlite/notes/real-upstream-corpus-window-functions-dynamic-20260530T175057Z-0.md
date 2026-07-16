## real-upstream-corpus-window-functions-dynamic-20260530T175057Z-0

Base accepted HEAD: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`

### Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Newly owned upstream range: `window4.test` `4.5.1.1` through `4.5.32.2`.
- Existing local corpus already covered `window4.test` `1.*`, `2.*`, `3.*`, and `windowE.test` dynamic frame cases. This patch extends the same file with the upstream `ttt(a,b,c)` two-window partition/frame matrix.

### Behavior Added

- Extended `SQLiteWindowDynamicUpstreamCorpusTest.php` with real upstream `window4.test` `4.5` partition/frame matrix checks for:
  - two simultaneous window aggregates over different `PARTITION BY` lists;
  - `RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`;
  - `RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING`;
  - `RANGE BETWEEN CURRENT ROW AND CURRENT ROW`;
  - `RANGE BETWEEN CURRENT ROW AND UNBOUNDED FOLLOWING`;
  - paired `max`/`min` and `sum` aggregate windows ordered by `a`.
- The tests use the real upstream `ttt` rowset and compare `SQLiteWindowFunction::aggregateFrameBetweenValues()` to a lane-local oracle that groups by the upstream partition terms and applies the upstream frame boundaries.

### Focused Evidence

- Before reference from prior accepted note for this corpus file: `45` PASS lines / `603` assertions.
- After focused run: `111` PASS lines / `1755` assertions.
- Honest focused delta: `+66` PASS lines / `+1152` assertions.

Commands:

- `php -l lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicUpstreamCorpusTest.php`

Result:

- `1 test files, 1755 assertions, 0 failures`

### Dependency Closure

No new support component is needed. This reuses lane-local `SQLiteWindowFunction` aggregate frame evaluation and the existing focused PHP harness.

### Non-Overlap

This does not add metadata-only rows or generated fake upstream names. It does not repeat the already accepted dynamic `nth_value`, `lead`/`lag`, simple boundary, `windowE`, or runner evidence slices. The new owned behavior is the upstream `window4.test` `4.5` two-window partition/frame matrix.
