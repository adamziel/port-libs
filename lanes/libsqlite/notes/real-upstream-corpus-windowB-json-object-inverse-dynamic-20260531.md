# Real Upstream Corpus: windowB JSON Object Inverse Dynamic

Micro-slice: `real-upstream-corpus-window-functions-dynamic-20260531T075150Z-0`

Accepted base: `9d7a6158784515939dbe96138a460121fe325c71`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test`
- Sections `3.8` through `3.16`

Behavior admitted:

- `json_group_object()` as a window aggregate over `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING`.
- NULL object labels are omitted from each frame.
- Sliding-frame removal preserves later non-NULL object entries after earlier NULL entries leave the frame, matching the upstream `xInverse()` regression coverage.
- Dynamic variants widen the same upstream behavior across frame widths, generated row counts, sparse NULL-label bands, and suffixed labels.

Focused evidence:

- Command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowBJsonObjectInverseDynamicTest.php`
- Result: `1 test files, 3010 assertions, 0 failures`
- PASS lines: `1010`

Non-overlap:

- Does not repeat accepted `window1`, `window2`, `window3`, `window9`, `windowA`, or earlier `windowB` RANGE/JSON-array rows.
- Does not add metadata-only rows or generated fake upstream script ids.
- Uses the existing generic `SQLiteJsonAggregate` window-frame implementation; no WordPress-specific or numbered production API is introduced.

Dependency closure:

- No new support component is needed. This reuses lane-local generic JSON aggregate and ROWS frame helpers.
