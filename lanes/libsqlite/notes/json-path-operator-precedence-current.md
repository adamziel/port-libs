# JSON Path Operator Precedence Current Slice

## Scope

- Fixed parser-level SELECT expression precedence so `||`, `->`, and `->>` are parsed in one left-associative tier, matching SQLite operator precedence for mixed JSON path and concatenation expressions.
- Preserved explicit-parentheses composition for WordPress-style copied `wp_options` JSON diagnostics.
- Added a WordPress smoke for JSON path operator precedence and dynamic RHS path construction.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonbPathOperatorIndexCurrentNext15Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-path-operator-precedence.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPathOperatorIndexCurrentNext15Test.php`
  - `1 test files, 37 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-path-operator-precedence.php --self-test`
  - `wordpress-json-path-operator-precedence self-test passed`

## Non-Overlap

This avoids JSON table cursor/source/hidden/visible constraint work, JSON aggregate/window work, JSONB malformed path diagnostics, and generated-index JSON operator planning. The slice is limited to current parser execution of scalar JSON path operators mixed with concatenation.

## Dependency Closure

No new support component is needed; this reuses the existing `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteJsonPath`, and JSONB/text decoding helpers.
