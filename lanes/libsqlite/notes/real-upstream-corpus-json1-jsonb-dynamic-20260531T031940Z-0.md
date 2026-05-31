# Real Upstream JSON501 JSON5 Dynamic Corpus

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T031940Z-0`

Base: `582d5b219b619868bb38159464dc8e8768230ba8`

Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`

Ported sections:

- `json501-1.*` IdentifierName object keys, including `$`, underscore, ASCII, and non-ASCII key names.
- `json501-2.*` object trailing commas.
- `json501-3.*` array trailing commas.
- `json501-4.*` single-quoted strings.
- `json501-5.*` escaped multiline strings, including LF, CR, CRLF, U+2028, and U+2029.
- `json501-6.*` JSON5 character escapes, including NUL, hex, and ordinary escaped characters.
- `json501-7.*` hexadecimal numbers.
- `json501-8.*` leading-dot, trailing-dot, signed, and exponent number forms.
- `json501-9.*` Infinity, Inf, NaN, QNaN, and SNaN variants.
- `json501-11.*` single-line and block comments.

Local coverage:

- Added `SQLiteRealUpstreamJson501Json5DynamicCorpusTest.php`.
- Adds 250 generated JSON5 documents, each with distinct keys, comments, numbers, string escapes, arrays, and nested objects.
- Adds 1002 focused TestRunner PASS cases and 6257 behavior assertions.
- Exercises `json_valid` flags, `json()`, `jsonb()`, `json_extract`, `->>`, `json_array_length`, `json_pretty`, strict JSON canonicalization, and JSONB parity.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible constraint work.
- This avoids existing JSON101 constructors, JSON102 multi-path extraction, JSON104 merge-patch, JSON105 reverse index, JSON106 invariant, JSON107 text-looking BLOB, JSON108 pretty bulk, and JSON109 array-insert dynamic corpus files.
- This slice is JSON501 JSON5 lexical/parser semantics plus JSONB canonical parity.

Dependency closure:

- No new support component is needed.
- Reuses existing native PHP JSON5, JSONB, canonicalization, extraction, validity, pretty, and SELECT expression helpers.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson501Json5DynamicCorpusTest.php`
  - `1 test files, 6257 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson501Json5DynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
