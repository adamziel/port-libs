# SQLite b-tree vacuum pointer-map freeblock current-source next879-894

Prepared next879-894 as the direct follow-on to completed next863-878 by extending the canonical
`SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan` current-source variant.

- Reuses `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`.
- No new numbered source class was added; the new slice-numbered factory methods delegate to the shared current-source variant.
- Scope is limited to current-source handoff receipts after the next431-446 freelist splice shape preserved through next863-878.
- The slice intentionally does not rework freelist splice construction, next261 vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext879894Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next879-894.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext879894Test.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext863878Test.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next879-894.php`
- `git diff --check`
