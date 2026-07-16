# real-upstream-corpus-json1-jsonb-dynamic-20260530T172025Z-0

- Base accepted HEAD: `99dfad49eb8b3659a920d2be780c5f32d787d8ac`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`.
- Focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`.

## Behavior Added

Added real upstream `json109.test` coverage for `json_array_insert()` and
`jsonb_array_insert()`:

- `json109-1.1` through `json109-1.9`: repeated current-index inserts,
  append-token inserts, forward indexes, reverse `[#-N]` indexes, and
  before-first no-op behavior.
- `json109-2.1` through `json109-2.8`: non-array element rejection, missing
  object member array creation, nested object creation for a final array
  element, root object no-op behavior, and later invalid path abort behavior.
- Each successful upstream case is asserted against text JSON and JSONB input.
  Error cases assert both text JSON and JSONB entry points reject the same
  malformed/non-array-element paths.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonDynamicPathCorpusTest.php`
- Result: `1 test files, 680 assertions, 0 failures`
- Focused assertion growth: `+160` assertions over the prior 520-assertion
  file shape.

## Non-Overlap

This slice is distinct from the prior accepted JSON dynamic batches:

- It does not repeat `json101.test`, `json102.test`, `json104.test`,
  `json105.test`, `json107.test`, `json501.test`, `json502.test`, or
  `jsonb01.test` coverage.
- It does not add JSON table cursor/source/constraint coverage, JSON
  aggregate/window coverage, source-neutral cleanup, or runner metadata rows.
- It ports only real upstream `json109.test` array-insert behavior.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON5 parsing,
JSONB encode/decode, JSON path handling, canonical JSON encoding, and
`SQLiteJsonArrayInsert`.

## Root Harness

Not run - isolated micro-slice.
