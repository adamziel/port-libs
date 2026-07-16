2026-05-27 - yield-sqlite-select-compound-order-limit-collate-next12

Implemented compound SELECT set-operator duplicate comparison using the left
arm's projected COLLATE metadata for UNION, INTERSECT, and EXCEPT. This is
separate from final ORDER BY COLLATE/LIMIT/OFFSET sorting, which was already
covered; the new behavior handles projected NOCASE/RTRIM equality for set
membership while preserving BINARY default behavior and UNION ALL duplicates.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteSelectCompound.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/src/SQLiteSelectProjection.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php`
- `php -l lanes/libsqlite/examples/application-select-compound-collate-set.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php`
  - `1 test files, 27 assertions, 0 failures`
  - 26 new TestRunner PASS cases
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCollationSetOperatorTest.php lanes/libsqlite/tests/SQLiteOrderByCollateNullsCorpusTest.php`
  - `2 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-select-compound-collate-set.php --self-test`
  - `application-select-compound-collate-set self-test passed`

Dashboard delta:

- `phpPass`: +26, from 3796 to 3822, based on the new focused PASS lines in
  `SQLiteCompoundCollationSetOperatorTest.php`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior
  coverage, not newly mapped upstream inventory.

Non-overlap:

- Avoids accepted ORDER BY COLLATE/NULLS final-sort coverage by targeting
  compound set membership equality under projected COLLATE.
- Avoids accepted SELECT SQL expression ORDER BY, GROUP BY text, comma LIMIT,
  subquery, JSON table, VFS, WAL, B-tree, and Unicode GLOB clusters.

Dependency closure:

- No new support component is needed. The patch reuses existing SELECT SQL
  parser/projection/query execution and compound row-array executor support.
