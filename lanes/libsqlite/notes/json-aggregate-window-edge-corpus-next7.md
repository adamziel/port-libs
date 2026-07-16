# JSON Aggregate Window Edge Corpus next7

- Added `SQLiteJsonAggregate::jsonGroupArrayWindowFrameRows()` and `jsonGroupObjectWindowFrameRows()` for bounded JSON aggregate window frames with SQLite-style `EXCLUDE NO OTHERS`, `EXCLUDE CURRENT ROW`, `EXCLUDE GROUP`, `EXCLUDE TIES`, and FILTER-like row suppression.
- Added JSONB dispatch helpers for the same frame corpus so `jsonb_group_array()` and `jsonb_group_object()` frame outputs remain binary JSONB values while text dispatch preserves JSON text.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateWindowEdgeCorpusTest.php` reported `1 test files, 35 assertions, 0 failures` with 33 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-json-aggregate-window-edges.php` reports copied `wp_options` JSON window summaries with peer exclusion and JSONB decoded frames.
- Non-overlap: this slice avoids accepted JSON object aggregate/window DISTINCT/ORDER/FILTER basics, generic `SQLiteWindowFunction` EXCLUDE/FILTER coverage, JSON table window ranking, and parser-level JSON table source/cursor work. It covers the narrower untested JSON aggregate payload/window peer-exclusion edge corpus.
- Dependency closure: no new support component is needed; existing JSON subtype, JSONB, and SQLite JSON constructor helpers are reused.
