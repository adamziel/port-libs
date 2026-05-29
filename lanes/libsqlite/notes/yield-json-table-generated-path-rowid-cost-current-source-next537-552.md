# JSON table generated path rowid cost current-source next537-552

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next537-552`.

Behavior: extends `SQLiteJsonTablePlan` generated-path rowid cost current-source aliases from next537 through next552 as a direct follow-on to the merged next521-536 preparation fence. The slice keeps current-source `json_tree()` generated-path rowid point-cost admission stable when xCurrent/xRowid alias, fingerprint, and order observations agree, and keeps changed copied source rows on the next-reader reprepare path.

WordPress path: `wordpress-json-table-generated-path-rowid-cost-current-source-next537-552.php` models copied `wp_options` generated JSON rule scans that reuse the current rowid point cost for the active row while detecting a changed next source path/generation.

Validation:
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext521536Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext537552Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next537-552.php`
- `php tools/run-tests.php SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext521536Test SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext537552Test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next537-552.php --self-test`

Dependency closure: no new support component needed; this composes the existing generated-path rowid yield guard and cost-selection alias machinery.

Non-overlap: limited to next537-552 generated-path rowid current-source cost aliases and the prior next536 handoff assertion. It does not change JSON table cursor/source/hidden/visible constraint work, SELECT/JOIN/GROUP/subquery/ORDER/LIMIT clusters, VFS/WAL/pager/B-tree/planner/PRAGMA/ATTACH/window/VDBE work, or unrelated team state.
