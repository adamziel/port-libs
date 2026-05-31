# real-upstream-corpus-json1-jsonb-dynamic-20260531T035130Z-0

Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- Sections ported: `json501-5.1..5.5`, `json501-6.1..6.8`,
  `json501-7.1..10.1`, `json501-11.1`, `json501-12.1..12.4`, and
  `json501-14.1..14.31`.

Added focused PHP coverage:

- `SQLiteRealUpstreamJson501StringNumberWhitespaceDynamicTest.php`
- Focused result: `1 test files, 27748 assertions, 0 failures`
- Coverage cluster: JSON5 string continuations, character escapes, hexadecimal
  and signed/decimal-point numeric forms, comments, extended whitespace, and
  raw control characters in strings across text JSON and JSONB.

Non-overlap:

- Does not repeat accepted JSON table cursor/source/hidden/visible-constraint
  work, JSONB remove-only `jsonb01` coverage, JSON103 aggregate/window
  behavior, JSON105 reverse-index mutation, or JSON502 escaped path matrices.
- Adds no WordPress/wp source text or domain-specific API.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  JSON5 parsing, JSON canonicalization, JSONB, extract, inspection, tree,
  validity, and error-position helpers.

Root harness:

- Not run - isolated micro-slice.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501StringNumberWhitespaceDynamicTest.php`
- Result: `1 test files, 27748 assertions, 0 failures`
