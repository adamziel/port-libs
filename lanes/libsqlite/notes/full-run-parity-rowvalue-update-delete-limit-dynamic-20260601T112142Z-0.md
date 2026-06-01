# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T112142Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- `SQLiteUpdateDeleteReturningSql` now parses `CAST(... AS type-name)` LIMIT
  expressions using SQLite type-name affinity rules instead of only exact
  one-word type names.
- Focused coverage includes parenthesized numeric type names such as
  `DECIMAL(10,2)`, multi-word integer and real type names such as
  `UNSIGNED BIG INT` and `DOUBLE PRECISION`, character type names such as
  `VARCHAR(10)` and `NATIVE CHARACTER(12)`, and default numeric custom type
  names.
- The same cast-affinity LIMIT values are exercised in outer UPDATE windows,
  comma-form DELETE windows, and row-value `IN (SELECT ... LIMIT ... OFFSET
  ...)` tuple sources.
- Nonintegral numeric/real/text cast results and BLOB-affinity type names
  remain rejected as datatype mismatches for LIMIT/OFFSET.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` for
  CAST type-name affinity behavior, especially the e_expr-27 cast-affinity
  sections.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  row-value tuple predicates over ordered limited subqueries.

Focused growth:

- Adds `SQLiteRowValueUpdateDeleteLimitCastAffinityDynamicTest.php` with 48
  distinct TestRunner PASS cases and 100 behavior assertions.
- Expected `phpPass` movement: +48, from 5,841,556 to 5,841,604.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCastAffinityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCastAffinityDynamicTest.php`
  - `1 test files, 100 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimit*DynamicTest.php`
  - `8 test files, 1827 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php`
  - `2 test files, 21513 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
  - `1 test files, 48 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. This reuses the existing native PHP
  UPDATE/DELETE RETURNING SQL parser, row-value tuple source evaluator, and
  LIMIT/OFFSET selection plan.

Non-overlap:

- This is limited to CAST type-name affinity inside dynamic UPDATE/DELETE
  LIMIT/OFFSET expressions. It does not repeat prior negative offset,
  arithmetic, exact one-word CAST type, boolean, scalar function, JSON,
  random, timediff, unistr, string-literal OFFSET-token, grouped SELECT,
  JSON table, WAL/VFS, B-tree, PRAGMA, trigger/FK, source-neutral cleanup, or
  metadata-only suite evidence surfaces.
