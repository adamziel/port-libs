# real-upstream-corpus-json1-jsonb-dynamic-20260601T051621Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Upstream sections `json101-12.110`, `json101-12.110b`,
  `json101-12.120`, `json101-12.120b`, and `json101-18.2` through
  `json101-18.5`.

Behavior ported:

- Added parser-level `SQLiteSelectSql` coverage for quoted JSON object labels
  containing dots and empty quoted object labels.
- The dynamic corpus drives text JSON and JSONB inputs through row-column path
  operands, `WHERE` filtering, `ORDER BY`, `LIMIT`, `json_extract()`,
  `json_type()`, `json_remove()`, `jsonb_remove()`, and `jsonb_extract()`.
- Added bare-dot path rejection through `SQLiteSelectSql` for both text and
  JSONB row sources.

Non-overlap:

- Existing accepted tests cover these upstream path rules through direct JSON
  helpers and `SQLiteSelectExpression`.
- This slice is limited to parser-level `SELECT` row-source dispatch and does
  not add metadata-only suite rows or fabricated upstream script IDs.

Dependency closure:

- No new support component is needed. The slice reuses existing
  `SQLiteSelectSql`, JSON path parsing, JSON1/JSONB scalar dispatch, and
  row-array predicate execution.

Verification:

- Passed:
  `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101QuotedPathSelectSqlDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson101QuotedPathSelectSqlDynamic20260601Test.php`
- Passed:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101QuotedPathSelectSqlDynamic20260601Test.php`
  - `1 test files, 12010 assertions, 0 failures`
  - 1202 distinct TestRunner PASS cases in this new focused file.
- Passed:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- Passed:
  `git diff --check -- lanes/libsqlite`
  - no output.

Expected lane-status movement:

- `phpPass`: `5548966 -> 5550168` from the 1202 new real upstream focused
  PASS cases.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this slice adds
  behavior assertions against already-hydrated upstream `json101.test` source.
