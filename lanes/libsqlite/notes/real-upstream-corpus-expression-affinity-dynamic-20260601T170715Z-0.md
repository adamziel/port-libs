# real-upstream-corpus-expression-affinity-dynamic-20260601T170715Z-0

Implemented a bounded upstream expression-affinity math scalar cluster from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test`
  - `func-4.17` and `func-4.18` dynamic `round()` loops.
  - `func-4.20` through `func-4.40` selected `round()` precision and large-value cases.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func7.test`
  - `func7-100`, `func7-110`, `func7-200`, `func7-210`.
  - PostgreSQL-derived `func7-pg-*` math cases through `func7-pg-550`.
  - MySQL-derived `func7-mysql-*` math cases through `func7-mysql-331`.

Behavior delta:

- `SQLiteCoreScalarFunction` now dispatches `degrees`, `radians`, `sinh`, `cosh`, `tanh`, `asinh`, `acosh`, and `atanh`.
- `ceil`, `ceiling`, `floor`, and `trunc` preserve SQLite's integer storage class when the lossless numeric input is integer.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicMathFunctions20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicMathFunctions20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicMathFunctions20260601Test.php`
  - `1 test files, 6575 assertions, 0 failures`
  - 2189 focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Status delta:

- `phpPass`: `6151056 -> 6153245` (`+2189`).
- Mapped coverage remains `1589 / 1589`.
- Root harness: not run, isolated micro-slice.

Non-overlap:

- This slice owns `func.test` `func-4` `round()` loops and `func7.test` math scalar expression dispatch only.
- It avoids already covered e_expr CASE/CAST/LIKE/GLOB, atof1 decimal windows, types2/types3 storage matrices, JSON, WAL, VFS, B-tree, PRAGMA, and source-neutral cleanup surfaces.

Dependency closure:

- Reuses existing `SQLiteSelectSql` and `SQLiteCoreScalarFunction`.
- No new support component is needed.
