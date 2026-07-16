# libsqlite JSON aggregate text RANGE window current

Slice: `libsqlite-json-aggregate-window-current`

Implemented a focused SQLite window-frame parity fix for JSON aggregate
windows ordered by nonnumeric/text keys. `RANGE BETWEEN UNBOUNDED PRECEDING
AND CURRENT ROW` and the default aggregate window frame now include prior peer
groups instead of collapsing to the current text peer group only. Bounded
`RANGE CURRENT ROW` behavior remains peer-only.

Changed behavior:

- `SQLiteSelectQuery::windowFrameRowBounds()` now honors unbounded preceding
  and unbounded following sides for nonnumeric `RANGE` keys.
- Added parser-level JSON aggregate coverage for `json_group_array()`,
  `jsonb_group_array()`, and `json_group_object()` with text `ORDER BY`
  windows, `FILTER`, aggregate `ORDER BY`, default frames, explicit unbounded
  frames, and peer-only current-row frames.
- Added `application-json-aggregate-text-range-window.php` smoke for copied
  `wp_options` text-bucket summaries.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateTextRangeWindowCurrentTest.php`
  - `1 test files, 30 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-aggregate-text-range-window.php --self-test`
  - passed
- `php -l` changed PHP files
  - passed
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- Avoids accepted JSON object aggregate/window DISTINCT/ORDER/FILTER cases,
  JSON table cursor/source/visible/hidden constraint work, and numbered
  current-source duplicate production helpers. This patch uses only the
  existing canonical `SQLiteSelectQuery` and adds no numbered production
  classes.

Dependency closure:

- No new support component needed; reuses native PHP SELECT SQL, JSON
  aggregate, JSONB, and window-frame execution.
