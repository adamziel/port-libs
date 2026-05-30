# Real Upstream Corpus Window Dynamic Frame Slice

- Session: `port-dev-sqlite-yield-dyn-real-window-20260530T224036Z`
- Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test`
- Ported scenario range: `window8.test` dynamic frame boundary families, especially `GROUPS`, `RANGE`, and `ROWS` combinations around `UNBOUNDED PRECEDING`, `<n> PRECEDING`, `CURRENT ROW`, `<n> FOLLOWING`, and `UNBOUNDED FOLLOWING`.

## Delta

Added one focused PHP TestRunner case to `SQLiteWindowDynamicFrameCorpusTest.php`:

- aggregate functions: `sum`, `count`, `total`, `avg`, `min`, `max`, `group_concat`
- value functions: `first_value`, `last_value`, `nth_value`
- frame modes: `GROUPS`, `RANGE`, `ROWS`
- dynamic rows: 20 value offsets across the upstream peer-key shape `[1, 1, 2, 3, 3, 3, 5, 8]`

The new block contributes `12,800` behavior assertions and one distinct focused PASS case. It does not add metadata-only rows, fake upstream script names, WordPress-specific APIs, or compatibility wrappers.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowDynamicFrameCorpusTest.php`
  - Result: `1 test files, 16359 assertions, 0 failures`
  - Before-current-file assertions inferred from the accepted file before this slice: `3559`
  - New focused behavior assertions: `12800`

## Dependency Closure

No new support component is needed. This reuses existing native `SQLiteWindowFunction` frame, aggregate, and value helpers.

## Non-Overlap

This slice avoids the already accepted window4 value/offset functions, window7 large peer groups, windowA/windowB NULL ordering, and existing `SQLiteWindowDynamicFrameCorpusTest` static frame assertions by adding a separate high-volume dynamic offset matrix over upstream `window8.test` frame families.
