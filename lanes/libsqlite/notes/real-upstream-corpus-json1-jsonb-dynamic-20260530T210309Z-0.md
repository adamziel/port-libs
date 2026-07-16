# real-upstream-corpus-json1-jsonb-dynamic-20260530T210309Z-0

- Base accepted HEAD: `6b3b48d963616c004933a32f66ee47ce4ec74885`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test` and `json102.test`.
- Ported behavior cluster: JSON1/JSONB dynamic document/path inspection over upstream `json101` top-level values, nested sample JSON documents, and `json102` `json_extract()` / `json_array_length()` path scenarios.
- New focused PHP test: `lanes/libsqlite/tests/SQLiteJsonDynamicUpstreamCorpusTest.php`.
- Focused coverage: 25 upstream-derived JSON documents x 30 path probes x 8 JSON1/JSONB assertions = 6000 distinct TestRunner PASS cases/assertions.
- Exercised behavior: `json_type()`, `json_array_length()`, `json_extract()`, `jsonb_extract()`, text input parity, JSONB input parity, missing path SQL NULL behavior, object/array/scalar result typing, and JSONB blob normalization for dynamic document/path combinations.
- Non-overlap: does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON aggregate/window, JSON path mutation, JSON107 legacy blob-text, JSON109 array-insert, or metadata-only upstream runner rows.
- Dependency closure: no new support component needed; the slice reuses existing native PHP JSON text, path, and JSONB helpers.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonDynamicUpstreamCorpusTest.php` => `1 test files, 6000 assertions, 0 failures`.
