# B-tree vacuum pointer-map freeblock current-source next232

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan`.
- Builds on accepted next229 resume-window rows and adds the next-writer handoff gate for vacuumed pointer-map/freeblock current-source reads.
- Proves handoff token chaining, pointer-map admission before payload pages, carried leaf freeblock receipts, monotonic admitted-page windows, and fenced tail pages after incremental vacuum.
- Application smoke: `examples/application-btree-vacuum-pointermap-freeblock-current-source-next232.php`.
- Focused test: `tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Test.php`.

## Verification

Commands run:

```bash
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Plan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Test.php
php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next232.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext232Test.php
php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next232.php
git diff --check -- lanes/libsqlite
```

## Non-overlap

This slice adds next-writer handoff admission after next229 resume-window receipts. It does not repeat next229 resume construction, next224 cursor sequencing, next218 write receipts, next212 apply ordering, overflow freelist release, page relocation, root collapse, or accepted freeblock materialization.

## Dependency Closure

No new support component is needed. The slice reuses existing native B-tree, pointer-map, overflow, table-leaf, record, and current-source cursor helpers.
