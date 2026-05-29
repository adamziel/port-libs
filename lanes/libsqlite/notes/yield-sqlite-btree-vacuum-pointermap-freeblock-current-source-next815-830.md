# SQLite b-tree vacuum pointer-map freeblock current-source next815-830

Prepared next815-830 as the direct follow-on to integrated next799-814 by extending the canonical
`SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` current-source variant.

- Reuses `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextFreelistCurrentSourceVariant`.
- No new numbered source class was added; the new slice-numbered factory methods delegate to the shared current-source variant.
- Scope is limited to current-source handoff receipts after the next431-446 freelist splice shape already exercised by next799-814.
- The slice intentionally does not rework freelist splice construction, next261 vacuum finalization, source-next links, overflow release, page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.

Validation:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext815830Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext815830Test.php`
- `git diff --check`
