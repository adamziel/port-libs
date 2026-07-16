# Real Upstream Subtype1 JSON Subtype Boundary Dynamic Slice

Micro-slice: `real-upstream-corpus-json1-jsonb-dynamic-20260601T194301Z-0`

Base accepted HEAD: `717fdb296ffb612f8a5e6c844680b41c0b18437c`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/subtype1.test`
- Covered sections: `subtype1-400`, `subtype1-510` through `subtype1-560`

Behavior added:

- `SQLiteSelectExpression` now evaluates `if()` / `iif()` lazily, so invalid JSON branches are not evaluated when a preceding `json_valid(...,6)` guard is false.
- `SQLiteJsonSubtypeValue` now behaves like its JSON text when SELECT expressions need SQL truthiness, integer/numeric unary coercion, or text conversion for `CAST(... AS TEXT)`.
- The dynamic corpus preserves subtype code `74` through `if()`, `CASE`, unary plus, and `COLLATE`, and verifies subtype loss through unary minus and `CAST`, matching the upstream subtype boundary scenarios.

Focused evidence:

- Red-first probe before the source fix: selected `subtype1-400` / `subtype1-550` branches errored on invalid JSON (`SQLite JSON5 value identifier is unsupported: not`), and `subtype1-530` errored because unary numeric operands rejected `SQLiteJsonSubtypeValue`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSubtype1JsonSubtypeBoundaryDynamic20260601Test.php`
- Result: `1 test files, 35011 assertions, 0 failures`
- PASS-line count: `1002` focused cases

Supplemental verification:

- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSubtype1JsonSubtypeBoundaryDynamic20260601Test.php`: no syntax errors.
- `php -r '$data=json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status.json valid JSON\n";'`: `lane-status.json valid JSON`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: `1 test files, 8 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicCaseIifTest.php`: `1 test files, 52231 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102SubtypeSelectSqlDynamic20260601Test.php`: `1 test files, 226009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php`: `1 test files, 72 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`: passed with no output.

Non-overlap:

- Avoids existing real-upstream `json102` lexical JSON/JSONB coverage, `json101`/`json103`/`json104`/`json105`/`json106`/`json107`/`json108`/`json109` dynamic corpus files, JSON table cursor/source/constraint slices, and accepted JSON visible/hidden constraint pushdown.
- This slice is specifically the `subtype1.test` JSON subtype boundary behavior around lazy guarded JSON operator evaluation and subtype stripping/preservation.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteSelectExpression`, JSON5 validation, JSON operators, `subtype()`, `CASE`, `COLLATE`, `CAST`, unary expression coercion, and the existing `TestRunner`.

Status delta:

- `phpPass` moves `6195392 -> 6230403` from the focused `35011` assertion pass.
- `phpFail` remains `16`; broad full-lane/release parity was not run in this isolated micro-slice.
