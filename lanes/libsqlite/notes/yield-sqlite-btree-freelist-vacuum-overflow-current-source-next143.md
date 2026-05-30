# B-tree freelist vacuum overflow current-source next143

## Behavior

Adds `SQLiteBTreeFreelistVacuumOverflowCurrentSourceNextPlan`, a bounded
current-source B-tree plan for the delete/vacuum/replacement path where copied
Application option rows have obsolete overflow chains at the database tail.

The plan records the original overflow chain rows, runs the accepted freelist
vacuum pointer-map plan, and then verifies that replacement overflow allocation:

- reuses only pages that survived incremental vacuum as freelist pages;
- never reuses tail pages truncated out of the database image;
- rewrites replacement overflow next-page links and pointer-map ownership for
  the surviving pages; and
- exposes current-source and replacement rows for focused runner evidence.

## Evidence

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistVacuumOverflowCurrentSourceNext143Test.php`

Result:

`1 test files, 309 assertions, 0 failures`

PASS-line delta: `+69` focused PASS lines in the new lane-scoped test file.

Application smoke:

`php lanes/libsqlite/examples/application-btree-freelist-vacuum-overflow-current-source-next143.php --self-test`

Result:

`application-btree-freelist-vacuum-overflow-current-source-next143 self-test passed`

## Non-overlap

This does not repeat accepted next139 freelist vacuum pointer-map allocation or
next140 freeblock vacuum current-source rows. It composes the freelist vacuum
path with explicit current-source overflow-chain evidence and replacement-chain
page-link checks for surviving-vs-truncated pages in the current source.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP
SQLite page-image, pointer-map, overflow-page, freelist-allocation, and vacuum
helpers.
