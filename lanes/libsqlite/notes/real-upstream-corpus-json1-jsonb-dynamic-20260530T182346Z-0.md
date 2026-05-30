# real-upstream-corpus-json1-jsonb-dynamic-20260530T182346Z-0

Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`.

Added focused real-upstream JSONB remove corpus coverage from hydrated upstream
SQLite source file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`
- `jsonb01-1.2.1` through `jsonb01-1.2.18`: `jsonb_remove()` and
  `json_remove()` parity for object member removal, nested object removal,
  array element removal, out-of-range no-op paths, append-token no-op paths,
  and reverse array indexes.
- `jsonb01-2.0`: malformed JSONB blob rejection.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01RemoveDynamicCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJsonb01RemoveDynamicCorpusTest.php`
  passed with `1 test files, 5409 assertions, 0 failures`.
- Focused PASS cases: `110`.
- Focused behavior assertions: `5409`, meeting the hard handoff floor via real
  upstream JSONB behavior assertions.

Non-overlap:

- This does not add metadata-only suite rows or fabricated `.test` ids.
- This does not repeat prior JSON table cursor/source/hidden/visible
  constraint work, JSON aggregate/window work, JSON102 constructor/extract
  batches, JSON105 dynamic mutation batches, JSON107 blob compatibility, or
  JSON109 array-insert coverage.
- The new assertions focus on `jsonb01.test` JSONB remove path parity and
  malformed JSONB rejection.

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  JSONB encoder/decoder, canonicalizer, inspection helpers, and remove-path
  implementation.
