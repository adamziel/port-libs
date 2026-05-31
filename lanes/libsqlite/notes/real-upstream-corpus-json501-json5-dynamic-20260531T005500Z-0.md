# real-upstream-corpus-json501-json5-dynamic-20260531T005500Z-0

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T005500Z-0`

Base accepted HEAD: `452a6f6fbb9dca50b40370a18b13b7d77ca03385`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json501.test`

Ported upstream behavior:

- `json501.test` sections 1.1 through 1.11: JSON5 identifier object keys, quoted path extraction, and invalid identifier rejection.
- `json501.test` sections 2.1 through 4.2: object/array trailing comma handling and single-quoted string/key behavior.
- `json501.test` sections 5.1 through 7.6: line continuations, JSON5 string escapes, and hexadecimal number extraction.
- `json501.test` sections 8.1 through 13.1: leading/trailing decimal forms, infinity/NaN canonicalization, explicit plus, comments, extended whitespace, and single-quoted text containing double quotes.
- `json501.test` section 14.1 through 14.31 loop: strict JSON rejection, JSON5 acceptance, `json()` canonical escaping, and `jsonb(...)->'$'` canonical escaping for control characters 0x01 through 0x1f.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusJson501Json5DynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusJson501Json5DynamicTest.php`
  - `1 test files, 226 assertions, 0 failures`

Non-overlap:

- Existing `SQLiteRealUpstreamJson1JsonbDynamicTest.php` covers json101/json102/json104/json105/json107/json109/jsonb01 behavior. This handoff adds a new focused real-upstream test file for json501 JSON5 syntax and control-character behavior.
- It does not add metadata-only rows, generated fake script ids, WordPress-specific scenarios, or source-neutral API renames.

Dependency closure:

- No new support component is required. The slice reuses existing native JSON5, JSONB, JSON path/extract, validity, error-position, and SELECT-expression helpers.
