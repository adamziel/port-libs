# B-tree root-collapse current-next15 pointer-map evidence

This isolated libsqlite slice fixes `SQLiteBTreeRootCollapsePlan` summary
reporting for auto-vacuum root collapse when the obsolete child free-page
update and adopted grandchild ownership update land on different pointer-map
pages. The page images were already rewritten, but `toArray()` only exposed
the freelist/free-page pointer-map pages; it now reports the union of freed
page pointer-map updates and adopted-grandchild pointer-map updates.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteBTreeRootCollapsePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeRootCollapsePlan.php

php -l lanes/libsqlite/tests/SQLiteBTreeRootCollapsePointerMapCurrentNext15Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteBTreeRootCollapsePointerMapCurrentNext15Test.php

php -l lanes/libsqlite/examples/application-btree-root-collapse-pointer-map-current-next15.php
No syntax errors detected in lanes/libsqlite/examples/application-btree-root-collapse-pointer-map-current-next15.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeRootCollapsePointerMapCurrentNext15Test.php
Focused test run: 1 selected test files (root lock skipped)
49 PASS lines
1 test files, 49 assertions, 0 failures

php lanes/libsqlite/examples/application-btree-root-collapse-pointer-map-current-next15.php
Reported `updated_pointer_map_page_numbers` `[2,105]`, obsolete child page 4
as `free-page`, adopted grandchild page 106 as `btree-page` parent 3, and the
surviving copied `siteurl` / `home` wp_options rows.
```

PASS delta: `+49` focused `TestRunner` PASS lines, so
`lane-status.json` `phpPass` moves from `4362` to `4411` in this isolated
worktree. `benchmarkDenominator.mapped` is unchanged because this is focused
native behavior coverage, not a newly mapped upstream inventory unit.

Non-overlap: avoids accepted B-tree table/index page relocation, overflow
freelist release, bulk overflow freeblock materialization, index-interior
merge, empty-leaf batch release, VFS writer/sync/rollback clusters, JSON table
cursor/source/constraint work, Unicode GLOB, SELECT SQL subqueries/grouping,
and suite evidence. This is a narrower root-collapse summary correctness fix
for pointer-map pages split across low and high page-number regions.

Dependency closure: no new support component is needed; the slice reuses
lane-local b-tree page assembly, pointer-map calculation, freelist free
planning, and SQLite database image primitives.
