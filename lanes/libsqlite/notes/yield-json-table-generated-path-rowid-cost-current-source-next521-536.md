# JSON table generated path rowid cost current-source next521-536

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next521-536`.

Behavior: extends `SQLiteJsonTablePlan` generated-path rowid cost current-source aliases from next521 through next536 as a direct follow-on to the merged next505-520 preparation fence. The slice keeps current-source `json_tree()` generated-path rowid point-cost admission stable when xCurrent/xRowid alias, fingerprint, and order observations agree, and keeps changed copied source rows on the next-reader reprepare path.

WordPress path: `wordpress-json-table-generated-path-rowid-cost-current-source-next521-536.php` models copied `wp_options` generated JSON rule scans that reuse the current rowid point cost for the active row while detecting a changed next source path/generation.

Validation:
- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext505520Test.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext521536Test.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next521-536.php`
- `php tools/run-tests.php SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext505520Test SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext521536Test`
- `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next521-536.php --self-test`

Dependency closure: no new support component needed; this composes the existing generated-path rowid yield guard and cost-selection alias machinery.

Non-overlap: limited to next521-536 generated-path rowid current-source cost aliases and the prior next520 handoff assertion. It does not change JSON table cursor/source/hidden/visible constraint work, SELECT/JOIN/GROUP/subquery/ORDER/LIMIT clusters, VFS/WAL/pager/B-tree/planner/PRAGMA/ATTACH/window/VDBE work, or unrelated team state.
