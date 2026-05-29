# B-tree Freelist Pointer-Map Vacuum Reuse Current Source Next117

## Scope

This slice covers the current-source to next-source boundary where obsolete
overflow pages are released to the freelist, incremental vacuum truncates only
the tail, and the next B-tree allocation reuses survivors that live under two
different auto-vacuum pointer-map pages.

- released overflow pages: `203`, `204`, `306`, `307`, `308`, `309`, `310`;
- incremental vacuum truncates tail pages `308`, `309`, `310`;
- survivor pages `204`, `307`, `306`, and trunk `203` are reused as B-tree
  table/index pages;
- pointer-map pages `105` and `208` are both rewritten from `free-page` to
  `btree-page` parent `42`;
- root-page allocation mode is also covered, proving parent `0` and
  `root-page` entries are materialized for reused vacuum survivors.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNext117Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNext117Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-pointermap-vacuum-reuse-current-source-next117.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistPointerMapVacuumReuseCurrentSourceNext117Test.php`
  - `1 test files, 259 assertions, 0 failures`
  - 67 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-pointermap-vacuum-reuse-current-source-next117.php`
  - emits JSON with allocated pages `[204,307,306,203]`, truncated pages
    `[308,309,310]`, and pointer-map page rewrites for pages `105` and `208`.

## Non-Overlap

This avoids accepted next104 single pointer-map-page vacuum survivor reuse,
next113 freelist trunk reuse, next115 overflow freeblock behavior, overflow
freelist release, page relocation, root collapse, index-interior merge,
bulk-overflow freeblocks, PRAGMA pointer-map diagnostics, and VFS/WAL storage
application. The new surface is multi-pointer-map auto-vacuum survivor reuse
after partial tail truncation.

## Dependency Closure

No new support component is needed. The patch composes existing native PHP
SQLite database-image, overflow release, incremental vacuum truncation,
freelist allocation, page materialization, and auto-vacuum pointer-map
primitives.

## Expected Status Delta

- `phpPass`: `45302 -> 45369` (+67 focused PASS lines)
- mapped coverage: unchanged at `604 / 1589`
- root harness: not run from this isolated micro-slice
