# Application schema migration transaction current-next30

Status: focused PHP behavior growth for copied `wp_options` schema migration transactions.

This slice adds `SQLiteSchemaMigrationTransactionPlan`, a bounded native PHP planner for SQLite copy-table schema migrations used by Application import/upgrade tooling when a table needs rebuilt without `ALTER COLUMN`. It plans `BEGIN IMMEDIATE`, optional FK disable/check/restore, create-copy-drop-rename, index/trigger recreation, `schema_version` bumping, dirty page/journal estimates, sync barriers, and rollback diagnostics.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaMigrationTransactionCurrentNext30Test.php
```

Expected dashboard movement:

- `phpPass`: +62 verified focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is lane-local PHP behavior for existing schema/transaction/VFS primitives, not a newly mapped upstream Tcl unit.

Application smoke:

```sh
php lanes/libsqlite/examples/application-schema-migration-transaction-current-next30.php
```

Non-overlap: this avoids accepted current import row planning, rollback-journal commit/apply, VFS sync/apply/file writer/lock/process-lock clusters, WAL checkpoint/savepoint byte truncation, super-journal commit, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, JSON table source/cursor/hidden/visible constraint work, Unicode GLOB, and B-tree page move/root collapse/overflow/freelist clusters. The behavior is specifically schema migration transaction planning for a copy-table rebuild.

Dependency closure: no new support component is needed. The slice reuses lane-local transaction begin lock planning and VFS sync-plan primitives; later pager integration can feed these statements into native file-handle page-image application.

Next task: wire the schema migration transaction plan into native schema catalog updates once the parser-level DDL executor owns writable sqlite_schema row images.
