# schema-alter-generated-check-reparse-current-source-next126

Status: focused PHP behavior growth for current-source ALTER TABLE ADD COLUMN
schema reparse.

This slice wires `SQLiteSchemaDdlReparsePlan` to apply bounded
`ALTER TABLE ... ADD COLUMN` statements through `SQLiteAlterTableColumnCorpus`.
Generated virtual columns and CHECK/NOT NULL constraints now scan the supplied
current table rows before sqlite_schema SQL is rewritten. The reparse result
advances the schema cookie once, refreshes `PRAGMA table_xinfo` samples, and
invalidates prepared statements compiled against the previous schema cookie.

Application path: `application-schema-alter-generated-check-reparse-current-source-next126.php`
models a copied `wp_options` import where plugin code adds a virtual lower-case
option-name column with CHECK validation. The smoke proves current rows are
validated, the generated column appears in `table_xinfo`, and only stale
prepared statements are invalidated.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php

php -l lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedCheckReparseCurrentSourceNext126Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedCheckReparseCurrentSourceNext126Test.php

php -l lanes/libsqlite/examples/application-schema-alter-generated-check-reparse-current-source-next126.php
No syntax errors detected in lanes/libsqlite/examples/application-schema-alter-generated-check-reparse-current-source-next126.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedCheckReparseCurrentSourceNext126Test.php
1 test files, 51 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `50809` to `50860` for the 51 verified
focused PASS lines. `benchmarkDenominator.mapped` is unchanged at `606 / 1589`;
this is focused PHP behavior over an existing schema/ALTER surface, not a newly
hydrated upstream Tcl inventory row.

Non-overlap: avoids accepted schema generated-trigger reparse, ALTER rename
column trigger/view rewriting, ALTER ADD generated CHECK row-scan corpus,
ATTACH/temp schema-cache work, PRAGMA schema catalog-only coverage, JSON,
WAL/VFS, B-tree, encoding, and SELECT SQL clusters. The new behavior is
current-source sqlite_schema reparse for ALTER ADD COLUMN using existing-row
generated CHECK validation and prepared statement invalidation.

Dependency closure: no new support component is needed. The slice reuses the
lane-local schema-record, schema-reparse, PRAGMA schema catalog, and ALTER
column corpus primitives.

Next task: expand current-source schema DDL reparse only if it adds a distinct
ALTER behavior with fresh focused assertions, such as constraint/index
dependency propagation after a successful ADD COLUMN.
