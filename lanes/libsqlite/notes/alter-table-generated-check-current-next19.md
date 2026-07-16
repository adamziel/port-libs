# ALTER TABLE generated CHECK current-row scan next19

2026-05-27 isolated slice `yield-sqlite-alter-table-generated-check-current-next19`.

- Behavior: `SQLiteAlterTableColumnCorpus::addColumn()` now accepts copied current rows and validates SQLite 3.37+ `ALTER TABLE ADD COLUMN` current-row scans for CHECK constraints, generated VIRTUAL columns, generated NOT NULL expressions, and NOT NULL defaults before schema SQL is rewritten.
- Application smoke: `examples/application-alter-generated-check-current-next19.php` validates copied `wp_options` rows for a generated `option_name_lower` column and reports a rejected generated length CHECK over an existing short option value.
- Non-overlap: avoids accepted ALTER ADD/DROP schema rewrite, generated-column catalog/check autoindex inspection, generated CASE dependency planning, SELECT SQL expression execution, and recent storage/VFS/B-tree/WAL clusters. This slice is limited to current-row validation during ALTER ADD COLUMN.
- Dependency closure: no new support component is needed; the implementation reuses the existing lane-local ALTER TABLE schema helper and a bounded expression evaluator for the ADD COLUMN validation subset.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAlterTableGeneratedCheckCurrentNext19Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 26 assertions, 0 failures
```
