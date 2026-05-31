# real-upstream-corpus-json102-operator-dynamic-20260531T004000Z

Slice: `real-upstream-corpus-json1-jsonb-dynamic-20260531T004000Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`

Ported upstream sections:

- `json102-1600`: object-member `->`, `->>`, and `json_extract()` parity for
  SQL NULL, integer, real, text, array, object, and missing member results.
- `json102-1610` and `json102-1620`: array-index `->` / `->>` parity for
  integer RHS operands, including JSON null versus missing index behavior.
- `json102-1800` through `json102-1831`: numeric-looking string RHS operands
  are object-member names, while integer RHS operands are array indexes.

Focused PHP test:

- `lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicTest.php`

Coverage summary:

- Adds 1,009 distinct focused TestRunner PASS cases.
- Adds 18,088 behavior assertions.
- Exercises text JSON and JSONB blob input parity through native
  `SQLiteSelectExpression`, `SQLiteJsonPath`, `SQLiteJsonExtract`,
  `SQLiteJsonInspection`, and `SQLiteJsonB`.
- Distinguishes missing path SQL NULL from JSON null text returned by the
  `->` operator.

Non-overlap:

- This does not repeat accepted JSON table cursor/source/hidden/visible
  constraint coverage, JSON103 aggregates/windows, JSON104 merge patch,
  JSON105/JSON109 mutation path coverage, JSON107 BLOB-text compatibility,
  JSON501/JSON502 lexical label/path work, or JSON102 lexical-boundary rows.
- The cluster is limited to real upstream JSON102 operator RHS semantics and
  JSON/JSONB parity.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicTest.php`
  -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102OperatorDynamicTest.php`
  -> `1 test files, 18088 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. The test reuses native PHP JSON path,
  JSONB, extraction, inspection, and SELECT expression operator support already
  present under `lanes/libsqlite/src`.
