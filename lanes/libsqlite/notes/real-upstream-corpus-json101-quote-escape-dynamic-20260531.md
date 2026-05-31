# real-upstream-corpus-json101-quote-escape-dynamic-20260531

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`

Ported sections:

- `json101-11.1` through `json101-11.6`: `json_quote()` SQL value coercion for text, real, integer, NULL, BLOB error behavior, and argument arity.
- `json101-12.101` through `json101-12.201`: `json_valid()` string escape classification.

Lane movement:

- Added `SQLiteRealUpstreamJson101QuoteEscapeDynamic20260531Test.php`.
- Focused TestRunner growth: `1001` PASS lines.
- Focused behavior assertions: `12003`.
- Expected selected throughput after integration: `2580336 -> 2581337` PHP PASS lines.
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:

This slice avoids accepted JSON102 mutation/operator, JSON103 aggregate/window,
JSON104 patch, JSON105/108 invariant, JSON107 blob compatibility, JSON109 array
insert, JSON501/502 JSON5/escaped-path, JSON table cursor/source/constraint,
and JSONB malformed/removal clusters. It focuses only on `json101.test`
`json_quote()` SQL-value coercion and string escape validity behavior.

Dependency closure:

No new support component is needed. The test reuses existing native
`SQLiteJsonQuote`, `SQLiteJsonValidity`, `SQLiteJsonB`, `SQLiteJsonExtract`,
and `SQLiteSelectExpression` helpers.

Verification:

- Red-first: initial focused run exposed a test-contract mismatch for SELECT
  expression `json_quote()` return shape: `1000` failures.
- Fixed by asserting the existing string return contract rather than a subtype
  wrapper.
- Passing focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101QuoteEscapeDynamic20260531Test.php`
  => `1 test files, 12003 assertions, 0 failures`.
