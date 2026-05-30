# Application tenant key-value import savepoint current-next37

Status: focused PHP corpus growth for copied application tenant key-value
imports inside per-tenant savepoint batches.

This slice now keeps `SQLiteTenantImportSavepointPlan` source-neutral: copied
import rows use `tenant_id`, `setting_id`, `key_name`, `key_value`, and
`load_policy`, route to `app_settings`, numbered `app_tenant_{id}_settings`
tables, and optional global `app_tenant_settings` batches. It prefixes
savepoint names by tenant id, isolates a rolled-back tenant batch from later
tenant imports, and namespaces dirty page numbers so pager/VFS follow-up work
can apply per-table page images without conflating tenants.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

```sh
php lanes/libsqlite/examples/application-tenant-keyvalue-import-savepoint-current-next37.php
```

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP row/savepoint planning and remains bounded to application
tenant key-value import batches.

Non-overlap: avoids accepted savepoint page-image rollback, VFS savepoint
rollback application, WAL byte truncation, rollback-journal commit/apply,
super-journal commit, JSON table source/cursor/constraints, SELECT SQL
subquery/group/order clusters, B-tree page relocation/root collapse/overflow
release, Unicode GLOB, and single-site bulk import savepoint current-next28.
