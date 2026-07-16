# ATTACH collation temp current next28

Status: focused PHP corpus growth for ATTACH/temp/current-source collation availability.

This slice adds `SQLiteAttachCollationTempCurrentPlan` for copied Application schemas after `ATTACH`. It resolves the current table using SQLite search order (`temp`, `main`, then attached databases), inspects only the indexes owned by that current schema, extracts each indexed term's `COLLATE` clause, and reports which indexes are usable or blocked by missing connection-registered collations.

Application relevance: copied `wp_options` imports often use temp staging tables while an attached site/archive database is open. The smoke shows temp `wp_options` shadowing main, an attached `wp_sitemeta` index requiring `wp_slug`, and the same current-source index becoming usable after the required custom collation is registered.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteAttachCollationTempCurrentPlan.php
php -l lanes/libsqlite/tests/SQLiteAttachCollationTempCurrentNext28Test.php
php -l lanes/libsqlite/examples/application-attach-collation-temp-current-next28.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachCollationTempCurrentNext28Test.php
php lanes/libsqlite/examples/application-attach-collation-temp-current-next28.php --self-test
git diff --check -- lanes/libsqlite
```

Focused output:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

Non-overlap: this does not repeat accepted ATTACH/DETACH lifecycle, temp schema shadowing, PRAGMA function/module/collation metadata, collation comparison/range semantics, Unicode GLOB, SELECT SQL text/subquery/group/order clusters, JSON table cursor/source/constraint work, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte truncation, or B-tree page move/root collapse/overflow release work. The behavior is limited to current-source index collation availability after temp/main/attached schema resolution.

Dependency closure: no new support component is needed; the slice reuses existing schema catalog records and bounded native PHP collation metadata.
