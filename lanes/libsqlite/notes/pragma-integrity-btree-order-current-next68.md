# PRAGMA integrity b-tree order current-next68

Slice: `pragma-integrity-current-next68`.

This patch extends native PHP `PRAGMA integrity_check` deep b-tree validation
with page-local key-order checks:

- table leaf rowids must be strictly increasing in cell-pointer order;
- table interior divider keys must be strictly increasing;
- index leaf and index interior records must be strictly increasing using the
  existing VDBE record comparator.

`PRAGMA quick_check` remains shallow for this surface, matching the existing
native split where quick checks report header/freelist/schema errors but skip
deep pointer-map/b-tree walking.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityBtreeOrderCurrentNext68Test.php
# 1 test files, 56 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityDeepCurrentNext19Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityBtreeOrderCurrentNext68Test.php
# 2 test files, 87 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-integrity-btree-order-current-next68.php
# reports copied wp_options rowid and option_name index key-order integrity errors
```

Expected dashboard movement: `phpPass` +56, from 25516 to 25572. Mapped
coverage remains unchanged because this is a native runtime behavior extension
over already mapped PRAGMA integrity/b-tree evidence.

Non-overlap: avoids accepted PRAGMA table-scope pagination, PRAGMA root/FK/
autoindex/pointer-map/freelist current-next yield surfaces, deep page parsing
guards, WAL/checkpoint/savepoint batches, JSON table planner/cursor surfaces,
and attach/temp schema cache work.

Dependency closure: no new support component is needed; this reuses existing
native SQLite page, cell, record, pointer-map, and VDBE comparison primitives.
