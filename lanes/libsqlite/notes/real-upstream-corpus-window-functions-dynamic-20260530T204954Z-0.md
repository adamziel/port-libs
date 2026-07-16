# Real Upstream Corpus Window Functions Dynamic

- Slice: `real-upstream-corpus-window-functions-dynamic-20260530T204954Z-0`
- Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `7.2-7.4`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` sections `10.1-10.6`
- Added PHP focused coverage:
  - `SQLiteRealUpstreamWindow1SalesLeadDynamicTest.php`
  - 1005 focused TestRunner PASS cases
  - 6007 behavior assertions
- Behavior covered:
  - `lead()` offsets and default values over ordered rows.
  - `row_number()` partitioned top-N sales ranking.
  - Partitioned cumulative and suffix `sum()` frames over regional sales rows.
  - Dynamic rotated/bumped data variants with LIMIT/OFFSET-style suffix slices.
- Non-overlap:
  - Avoids accepted `window4`, `window8`, `window9`, `windowerr`, JSON table window ranking, grouped SELECT text, expression ORDER BY, and prior frame-boundary corpus files.
  - Uses the previously uncovered `window1.test` sales and lead sections instead of metadata-only admission rows.
- Dependency closure:
  - No new support component needed; coverage reuses native `SQLiteWindowFunction` ranking, lead, and aggregate frame helpers.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SalesLeadDynamicTest.php`
  - `1 test files, 6007 assertions, 0 failures`
