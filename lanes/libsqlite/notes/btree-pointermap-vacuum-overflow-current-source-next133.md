# B-tree Pointer-map Vacuum Overflow Current Source Next133

This slice adds `SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan`.
It covers the current-source boundary where a Application-sized option delete
releases tail overflow pages, incremental vacuum truncates those overflow pages
and the auto-vacuum pointer-map page between them, then the next overflow
allocation appends pages across the same boundary and recreates the pointer-map
page with fresh overflow ownership.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumOverflowCurrentSourceNext133Test.php`
- Result: `1 test files, 269 assertions, 0 failures`
- PASS-line delta: `+77`

Application smoke:

- `php lanes/libsqlite/examples/application-btree-pointermap-vacuum-overflow-current-source-next133.php`
- Reports released overflow pages `104,106`, vacuum-truncated pages
  `106,105,104`, recreated pointer-map page `105`, reallocated overflow pages
  `104,106`, and final page count `106`.

Non-overlap: avoids accepted overflow freelist release, overflow freeblock
materialization, pointer-map free-page/reuse, freeblock/vacuum merge,
freelist trunk reuse, rootpage reuse, page relocation, root collapse, and
next131 overflow/freeblock allocation. The new surface is specifically the
vacuum-truncated pointer-map page being recreated by the following overflow
append allocation.

Dependency closure: no new support component is needed. The patch composes
existing native PHP overflow release, freelist tail truncation, pointer-map
image, and overflow allocation helpers.
