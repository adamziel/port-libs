## B-tree Pointer-Map Vacuum Current/Next 65

This slice implements auto-vacuum tail truncation across a pointer-map page
that has become the physical tail boundary after adjacent freelist pages are
removed. SQLite pointer-map pages are never placed on the freelist, but VACUUM
or incremental-vacuum current/next planning must still be able to drop an
obsolete pointer-map page when every page above it has already been truncated.

Behavior:

- `SQLiteDatabase::planFreelistTailTruncation()` now treats a tail pointer-map
  page as truncatable physical metadata instead of stopping the truncation
  scan.
- The freelist count is decremented only for pages that were actually on the
  freelist, so dropped pointer-map pages do not corrupt header freelist
  accounting.
- Bounded truncation still works: a one-page pass stops before the pointer-map
  page, a two-page pass can drop the pointer-map page while leaving a lower
  freelist trunk, and a full pass removes the lower trunk and clears the
  freelist.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumCurrentNext65Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 45 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapTruncateVacuumCurrentNext54Test.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumCurrentNext65Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 110 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-btree-pointermap-vacuum-current-next65.php
```

Non-overlap: this does not repeat accepted table/index page relocation,
root-collapse, index-interior merge, overflow freelist release, bulk overflow
freeblocks, or the current-next54 overflow freelist release plus tail
truncation flow. The new behavior is specifically the current/next VACUUM
boundary where a pointer-map page itself becomes obsolete and must not block
tail truncation.

Dependency closure: no new support component is needed; this reuses the
existing native SQLite database header, freelist trunk, and pointer-map helpers.
