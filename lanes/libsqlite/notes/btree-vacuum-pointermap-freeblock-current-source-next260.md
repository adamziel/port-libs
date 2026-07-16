# B-tree vacuum pointer-map freeblock current-source next260

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext260Plan`, a reader-visible current-source handoff after the accepted next257 source-advance fence. The new layer proves that copied `wp_options` overflow-delete readers only see reusable freeblock pages after their group pointer-map snapshot is visible, while truncated tail pages remain blocked from reader admission.

The plan reports:

- handoff pages and reader-visible pages;
- pointer-map snapshot pages and reusable freeblock snapshot pages;
- reader-visible page groups;
- advance-token continuity from next257;
- reader-source epoch preservation;
- freeblock receipt visibility and tail-page reader fencing.

## Application Smoke

`lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next260.php` models deleting an overflow-backed copied `_transient_next260` row from `wp_options`. It verifies that pointer-map pages `2` and `105` are snapshot-visible before reusable pages `3`, `106`, `107`, and `108`, and that obsolete tail pages `109` and `110` stay hidden from readers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext260Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext260Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next260.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext260Test.php`
  - `1 test files, 1409 assertions, 0 failures`
  - `149` PASS lines

## Non-Overlap

This slice starts after next257 advance fencing. It does not repeat next257 source advance, next256 publication fencing, next253 grouped apply ordering, next249 allocation publication, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, WAL/VFS behavior, or the accepted batch220 next256/next257 B-tree surfaces.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP SQLite database images, table leaf delete/freeblock materialization, overflow-chain metadata, auto-vacuum pointer-map rows, and current-source advance tokens.
