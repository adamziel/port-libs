# real-upstream-corpus-json1-jsonb-dynamic-20260531T082228Z-0

Base accepted HEAD: `b9873c852a7f5b8dd171221d5d3abd96ee2031c8`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json109.test`
- Related parity anchors: `json102.test` JSON extraction/inspection semantics and `jsonb01.test` JSONB validity/inspection semantics.

Owned upstream sections:

- `json109-1.1` two inserts at index zero preserve left-to-right edit order.
- `json109-1.2` prepend plus `$[#]` append in one `json_array_insert()` call.
- `json109-1.3` through `json109-1.5` positive array-index insertion.
- `json109-1.6` through `json109-1.8` `$[#-N]` reverse array-index insertion.
- `json109-1.9` reverse index before the first element is a no-op.

Patch movement:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php`.
- Adds `1002` focused TestRunner PASS cases and `14007` behavior assertions.
- Exercises parser-level `SQLiteSelectSql` dispatch over row-sourced JSON text and JSONB values, not just direct helper calls.
- Validates text/JSONB `json_array_insert()` parity, `json_extract()` original-row path binding, `jsonb_extract()` root BLOB extraction, `json_array_length()`, `json_type()`, strict JSONB validity, and JSONB inspection of the inserted result.

Non-overlap:

- Existing JSON109 direct-helper coverage owns successful array-insert helpers and the error matrix. This slice owns SQL text execution through `SQLiteSelectSql` over dynamic rows.
- It does not repeat JSON table cursor/source/constraint coverage, JSON104 merge-patch coverage, JSON106 invariant coverage, JSON501/JSON502 JSON5 lexical coverage, JSONB remove coverage, or suite-runner metadata rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php`
  - `1 test files, 14007 assertions, 0 failures`
  - PASS lines: `1002`

Dashboard/counting expectation:

- Count as focused PHP PASS-line growth only: `+1002`.
- Mapped denominator remains `1589 / 1589`; this does not add a new upstream inventory unit.

Dependency closure:

- No new support component needed. This reuses existing native `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteJsonArrayInsert`, `SQLiteJsonB`, `SQLiteJsonCanonical`, and JSON inspection/validity helpers.

Root harness:

- Not run - isolated micro-slice.
