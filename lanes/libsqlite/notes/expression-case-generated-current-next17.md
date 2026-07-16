# Expression CASE Generated Columns Current Next17

- Scope: bounded upstream-style generated-column schemas whose generated expressions contain simple and searched `CASE` forms, quoted identifiers, string-literal keyword noise, and direct/indirect generated-column loops.
- Non-overlap: avoids accepted SELECT CASE projection execution, SELECT SQL expression `ORDER BY`, generated-column dependency-cycle baseline coverage, and generated-column check/autoindex catalog cases by focusing on CASE-heavy generated-column dependency extraction and generated UNIQUE autoindex preservation.
- Application path: copied `wp_options` schemas derive cache route columns from `option_name`, `autoload`, and generated lower/rank/kind columns before import/catalog diagnostics, without requiring ext/sqlite.
- Dependency closure: no new support component is needed; this reuses the existing bounded `SQLiteGeneratedColumnDependencyPlan` and `SQLiteCreateTable` schema parsers.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionCaseGeneratedCurrentNext17Test.php` -> `1 test files, 56 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteGeneratedColumnDependencyCycleCorpusTest.php lanes/libsqlite/tests/SQLiteExpressionCaseGeneratedCurrentNext17Test.php` -> `2 test files, 88 assertions, 0 failures`.
  - `php -l lanes/libsqlite/src/SQLiteGeneratedColumnDependencyPlan.php && php -l lanes/libsqlite/tests/SQLiteExpressionCaseGeneratedCurrentNext17Test.php && php -l lanes/libsqlite/examples/application-generated-case-current-next17.php` -> no syntax errors.
  - `php lanes/libsqlite/examples/application-generated-case-current-next17.php` -> emitted status `ok`, evaluation order `option_name_lower`, `autoload_rank`, `option_kind`, `option_route`, and autoindex `option_route`.
  - `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";' && git diff --check -- lanes/libsqlite` -> `lane-status json ok` and no diff-check output.
