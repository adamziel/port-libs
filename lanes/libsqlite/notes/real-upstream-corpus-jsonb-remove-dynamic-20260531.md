## real-upstream-corpus-json1-jsonb-dynamic-20260531T060257Z-0

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`.

Added focused PHP coverage for upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/jsonb01.test`, section `jsonb01-1.2.*`, covering `jsonb_remove(x,$path)`, `json_remove(x,$path)`, JSONB/text input parity, SELECT expression `json(jsonb_remove(...))` dispatch, missing-path no-op behavior, append pseudo-index no-op behavior, and negative array index removals.

Focused assertion movement:

- New test file: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusJsonbRemoveDynamicTest.php`
- Focused result: `1 test files, 1029 assertions, 0 failures`
- TestRunner PASS cases: 203

Non-overlap:

- This batch does not repeat accepted JSON501 JSON5 canonicalization, JSON105 negative-index mutation, JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window behavior, or JSONB path-operator malformed-source coverage.
- The dynamic replay extends the real `jsonb01.test` negative-index/remove path table over varied array lengths while preserving the same upstream behavior shape.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP `SQLiteJsonB`, `SQLiteJsonRemove`, `SQLiteJsonCanonical`, and `SQLiteSelectExpression` helpers.
