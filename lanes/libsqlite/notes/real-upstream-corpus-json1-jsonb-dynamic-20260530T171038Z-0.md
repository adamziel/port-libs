# Real Upstream JSON Dynamic Path Corpus

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260530T171038Z-0`
Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`

## Upstream Source

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
- Ported scenario ranges:
  - `json105-1.10` through `json105-1.110`: `json_extract()` with `[#]`, `[#-N]`, padded reverse indexes, nested reverse indexes, huge reverse indexes, and multi-path extraction.
  - `json105-2.10` through `json105-2.140`: `json_remove()` dynamic append/reverse path no-op/removal ordering.
  - `json105-3.10` through `json105-3.40`: `json_insert()` append-token mutations.
  - `json105-4.10` through `json105-4.80`: `json_set()` append and reverse-index replacement mutations.
  - `json105-5.10` through `json105-5.80`: `json_replace()` append no-op and reverse-index replacement mutations.
  - `json105-6.10` through `json105-6.50`: malformed dynamic path rejection.

## Focused Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`.
- Focused result before final lint/diff checks:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`
  - `1 test files, 520 assertions, 0 failures`
  - `52` PASS lines.

## Non-Overlap

This slice does not add JSON table cursor/source/constraint coverage, JSON aggregate/window coverage, source-neutral cleanup, or metadata-only runner rows. It focuses only on upstream `json105.test` dynamic JSON path behavior for scalar JSON helpers and JSONB-compatible entry points.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP JSON parser, JSONB encoder/decoder, JSON path parser, mutation helpers, and focused PHP test harness.
