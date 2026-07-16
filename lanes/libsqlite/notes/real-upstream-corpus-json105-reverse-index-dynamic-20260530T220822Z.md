# real-upstream-corpus-json105-reverse-index-dynamic-20260530T220822Z

- Base accepted HEAD: `982e8dd8663ac2abd3a38d17e45a83e32b2f3371`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`.
- Ported sections: `json105-1.10` through `json105-1.110`, `json105-2.10` through `json105-2.140`, `json105-3.10` through `json105-3.40`, `json105-4.50` through `json105-4.80`, `json105-5.50` through `json105-5.80`, and malformed path checks `json105-6.10` through `json105-6.50`.
- New focused PHP test: `lanes/libsqlite/tests/SQLiteJson105ReverseIndexDynamicCorpusTest.php`.
- Focused coverage: 10 upstream-shaped JSON documents with reverse-index and append pseudo-index extract/remove/mutation path sets, across text input, JSONB input, JSON-returning functions, and JSONB-returning functions.
- Focused assertion/PASS count: `2850` distinct TestRunner assertions/PASS cases.
- Exercised behavior: `json_extract()`, `jsonb_extract()`, `json_remove()`, `jsonb_remove()`, `json_insert()`, `jsonb_insert()`, `json_set()`, `jsonb_set()`, `json_replace()`, `jsonb_replace()`, multi-path ordering, `[#]` append targeting, `#-N` reverse array indexes, zero-padded reverse indexes, nested reverse indexes, scalar-path misses, and malformed reverse-index path rejection.
- Non-overlap: does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON aggregate/window behavior, `json101/json102` broad document/path inspection, JSON104 patch coverage, JSON107 legacy blob-text, JSON109 array-insert behavior, or metadata-only upstream runner rows.
- Dependency closure: no new support component needed; this reuses the native PHP JSON path, JSONB, extract, remove, and mutation helpers.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJson105ReverseIndexDynamicCorpusTest.php` => `1 test files, 2850 assertions, 0 failures`.
