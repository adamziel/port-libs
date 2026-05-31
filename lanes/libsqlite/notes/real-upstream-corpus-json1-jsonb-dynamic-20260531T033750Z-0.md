# real-upstream-corpus-json1-jsonb-dynamic-20260531T033750Z-0

Base accepted HEAD: `eb22516d8f29af7145a28b1cc2453b19311c1d0b`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`
- Sections ported: `json501-8.6..8.11`, `json501-9.1..9.4`, `json501-10.1`, `json501-11.1`, `json501-12.1..12.4`, and `json501-14.1..14.31`.

Added focused PHP coverage:

- `SQLiteRealUpstreamJson501NumericWhitespaceControlDynamicTest.php`
- 1001 distinct TestRunner PASS cases
- 8229 behavior assertions
- Coverage cluster: JSON5 numeric canonicalization and extraction for trailing/leading decimal exponents, infinities, NaN, and explicit plus integers; JSON5 block/line comments and extended Unicode whitespace; raw control characters inside JSON5 string literals; JSONB parity; and SELECT `json()` expression dispatch.

Non-overlap:

- Does not repeat accepted JSON table cursor/source/hidden/visible-constraint work.
- Does not repeat prior JSON102 mutation/extract, JSON103 aggregate/window, JSON104 patch, JSON105 reverse-index, JSON108 pretty, JSON109 array-insert, or JSON501/502 escaped path stress coverage.
- Adds no WordPress/wp source text or domain-specific API.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP `SQLiteJson5Parser`, `SQLiteJsonCanonical`, `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, `SQLiteJsonExtract`, JSONB encoding, and SELECT expression dispatch helpers.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501NumericWhitespaceControlDynamicTest.php`
- Result: `1 test files, 8229 assertions, 0 failures`

Root harness:

- Not run - isolated micro-slice.
