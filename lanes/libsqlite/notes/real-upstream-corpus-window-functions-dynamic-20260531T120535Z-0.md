# real-upstream-corpus-window-functions-dynamic-20260531T120535Z-0

Session: `port-dev-sqlite-yield-dyn-real-window-20260531T120535Z`
Base accepted HEAD: `e4074c45f1e9d3c2408ad3ef65aec8f4e6ec75cf`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Ported sections: `window1.test` `78.1-78.2` and `79.1-79.3`.
- Owned behavior: `group_concat()` used as a window must preserve an
  empty-string result separately from an empty-frame `NULL`, and very large
  `FOLLOWING` frame bounds must terminate with suffix counts instead of
  expanding into runaway work.

## Focused Coverage

- Added `SQLiteRealUpstreamWindow1GroupConcatEmptyDynamic20260531Test.php`.
- The test uses `SQLiteSelectSql` so it exercises parser-level window
  aggregate dispatch, scalar `quote()`, `RANGE` frame bounds, and ordinary
  aggregate/window interaction.
- Dynamic coverage generates 1000 real-behavior cases over generic
  `app_series` rows, including empty-string, `NULL`, quoted text, and
  large-following suffix count expectations.
- Focused result:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1GroupConcatEmptyDynamic20260531Test.php`
  -> `1 test files, 5006 assertions, 0 failures`, with `1005` distinct PASS
  lines.

## Non-Overlap

This slice avoids the accepted `window1` named count, chained/named window,
regional sales, subquery/filter, RANGE-offset, and lead/limit batches. It also
does not repeat `window2` following-frame batches, `window3`/`window4` matrix
coverage, `window5` custom functions, `window6`/`window7`/`window8` frame
matrices, `window9`, `windowA`-`windowE`, error/fault/pushdown batches, or any
metadata-only runner evidence. The owned residual is only upstream
`window1.test` sections 78 and 79.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`SQLiteSelectSql`, `SQLiteWindowFunction`, and scalar `quote()` paths.
