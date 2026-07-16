# real-upstream-corpus-json501-control-character-dynamic-20260531T033349Z-0

Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`

This slice ports a non-overlapping JSON1/JSONB dynamic corpus cluster from the
hydrated upstream SQLite checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- Upstream sections `12.1` through `12.4`: JSON5 extended whitespace before
  objects, after colons, between members, and inside arrays.
- Upstream sections `14.$c.1` through `14.$c.4`: raw control characters inside
  JSON5 string literals are accepted by JSON5 mode, rejected by strict JSON
  validation, and canonicalized when routed through `json()` / `jsonb()`.

Added focused test file:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJson501ControlCharacterDynamicTest.php`

Focused assertion movement:

- Adds 1,178 focused PHP behavior assertions.
- The test matrix covers control characters `0x01` through `0x1f`, extended
  JSON5 whitespace codepoints from `json501.test`, `json_valid()` flag
  behavior, `json_error_position()`, text and JSONB canonicalization,
  `json_extract()` / `jsonb_extract()`, uppercase SQL function dispatch, and
  argument-vector dispatch.

Non-overlap:

- This does not repeat accepted JSON105 reverse-index mutation/extract/remove
  coverage, JSON table cursor/source/hidden/visible-constraint work, JSONB
  malformed planner diagnostics, JSON aggregate/window behavior, or status-only
  suite evidence.
- The behavior is directly sourced from `json501.test` JSON5 control-character
  and whitespace sections.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  JSON5 parser, JSON canonicalizer, JSONB codec, validity checker,
  error-position helper, and extractor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501ControlCharacterDynamicTest.php`
  - `1 test files, 1178 assertions, 0 failures`
