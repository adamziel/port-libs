# JSON Boolean Operators Current Next24

This slice adds parser-level SQLite truth-value predicates for JSON operator results:

- `WHERE option_value ->> '$.plugin.enabled'`
- `WHERE NOT option_value ->> '$.plugin.enabled'`
- `AND` / `OR` composition over JSON `->>` booleans, numeric extracts, text numeric prefixes, SQL NULL, and JSONB rows
- HAVING and CASE expression paths that depend on those predicates

The behavior is intentionally disjoint from accepted JSON path extraction, JSON table visible/hidden constraints, JSON table cursor/source wiring, expression `ORDER BY`, SELECT subqueries, and batch21 JSON table LEFT JOIN rowid aliases. No new support component is needed; this reuses the existing `SQLiteSelectSql`, `SQLiteSelectPredicate`, `SQLiteSelectExpression`, JSONB, and JSON path helpers.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonBooleanOperatorsCurrentNext24Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 52 PASS lines
# 1 test files, 52 assertions, 0 failures

php lanes/libsqlite/examples/application-json-boolean-operators.php
# enabledOptions: ["plugin_cache_settings"]
# needsReviewOptions: ["plugin_forms_settings","plugin_empty_settings"]
# priorityOptions: ["plugin_cache_settings"]

php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteSelectPredicate.php
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
php -l lanes/libsqlite/tests/SQLiteJsonBooleanOperatorsCurrentNext24Test.php
php -l lanes/libsqlite/examples/application-json-boolean-operators.php
# No syntax errors detected in changed PHP files

git diff --check -- lanes/libsqlite
```
