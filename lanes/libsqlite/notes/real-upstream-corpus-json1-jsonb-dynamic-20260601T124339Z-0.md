# real-upstream-corpus-json1-jsonb-dynamic-20260601T124339Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Upstream sections `json102-190` through `json102-240` and `json102-510`
  through `json102-600`.

Behavior ported:

- Added parser-level `SQLiteSelectSql` coverage for JSON inspection functions
  over host rows with column-supplied path operands.
- Exercises `json_array_length()` over root arrays, scalar array elements,
  object roots, object-member arrays, and missing paths.
- Exercises `json_type()` over root objects, root-path lookups, array paths,
  integer, real, true, false, null, text, and missing path results.
- Every row checks text JSON and stored JSONB column parity through `WHERE`
  filtering, `ORDER BY`, and `LIMIT`.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionSelectSqlDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionSelectSqlDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102InspectionSelectSqlDynamic20260601Test.php`
  - `1 test files, 34007 assertions, 0 failures`
  - `1002` focused TestRunner PASS cases.

Countability:

- `phpPass`: `5892006 -> 5893008`
- Focused assertion growth: `34007`
- Mapped upstream denominator remains complete at `1589 / 1589`; this is
  behavior growth against already hydrated upstream `json102.test`.

Non-overlap:

- Existing JSON102 coverage already checks this inspection matrix through
  direct helpers, `SQLiteSelectExpression`, and literal SELECT SQL rows.
- This slice is limited to parser-level SELECT execution over ordinary host
  tables with column path operands and stored JSONB columns.
- It does not repeat JSON table cursor/source/hidden/visible constraints,
  JSON102 subtype/tree projection/search rows, JSON101 valid/type root rows,
  JSON mutation/path/operator batches, JSON aggregate/window batches, or
  malformed JSONB diagnostics.

Dependency closure:

- No new support component is needed. The test reuses `SQLiteSelectSql`,
  `SQLiteJsonInspection`, and `SQLiteJsonB`.

Root harness:

- Not run - isolated micro-slice.
