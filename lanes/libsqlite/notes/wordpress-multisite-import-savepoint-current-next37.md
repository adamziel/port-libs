# Application multisite import savepoint current-next37

Status: focused PHP corpus growth for copied Application multisite option imports
inside per-blog savepoint batches.

This slice adds `SQLiteMultisiteImportSavepointPlan`, a bounded native
PHP planner that routes copied import rows to `wp_options`, numbered
`wp_{blog_id}_options` tables, and optional network `wp_sitemeta` batches. It
uses the existing single-site bulk import savepoint planner per blog, prefixes
savepoint names by blog id, isolates a rolled-back blog batch from later blog
imports, and namespaces dirty page numbers so pager/VFS follow-up work can apply
per-table page images without conflating sites.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteMultisiteImportSavepointCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

```sh
php lanes/libsqlite/examples/application-multisite-import-savepoint-current-next37.php
```

Dependency closure: no new support component is needed. The slice reuses
lane-local import transaction and bulk savepoint primitives and remains bounded
to native PHP row/savepoint planning for copied Application multisite imports.

Non-overlap: avoids accepted savepoint page-image rollback, VFS savepoint
rollback application, WAL byte truncation, rollback-journal commit/apply,
super-journal commit, JSON table source/cursor/constraints, SELECT SQL
subquery/group/order clusters, B-tree page relocation/root collapse/overflow
release, Unicode GLOB, and single-site bulk import savepoint current-next28.
