# B-tree Overflow Pointer-map Rebalance Current Source Next118

Adds `SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNextPlan`, a bounded
native PHP current-source/next view over the applied table-leaf delete rebalance
path where obsolete overflow pages are released and then reused for a replacement
overflow chain.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowPointerMapRebalanceCurrentSourceNext118Test.php`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-overflow-pointermap-rebalance-current-source-next118.php`
  - `Application btree118 transition pages: 7,8,9`
  - `Application btree118 current types: first-overflow-page,overflow-page,none`
  - `Application btree118 next types: overflow-page,first-overflow-page,overflow-page`
  - `Application btree118 roles: obsolete-reused,obsolete-reused,replacement-appended`
  - `Application btree118 replacement pages: 8,7,9`

Non-overlap:

Avoids accepted bulk overflow freeblocks, overflow freelist release, overflow
freepage allocation summaries, page relocation, root collapse, index-interior
merge, delete/rebalance overflow freeblock current-source next115, and
overflow rebalance cell apply current-source next107. The new surface is the
composed pointer-map transition rows across current overflow ownership,
free-page release, and replacement-chain ownership after the rebalance applies.

Dependency closure:

No new support component is needed. The slice composes existing native PHP
SQLite database image, table leaf/interior page, overflow chain, freelist,
pointer-map, and rebalance apply helpers.
