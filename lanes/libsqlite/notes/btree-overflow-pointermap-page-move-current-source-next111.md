# B-tree Overflow Pointer-map Page Move Current Source Next111

## Behavior

- Adds `SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan` for an auto-vacuum page move where the last database page is a non-first overflow page.
- The plan allocates a lower freelist slot, copies the source overflow page into that slot, rewrites the previous overflow page's next pointer to the moved page, retargets the moved page's pointer-map entry to `overflow-page` owned by the previous overflow page, and lowers the database page count.
- This is narrower than accepted table/index B-tree page relocation and overflow freelist release: it covers the overflow-chain predecessor-next rewrite for the moved overflow page itself, not moving a B-tree leaf or freeing obsolete overflow pages.

## Evidence

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNext111Test.php`
- Result: `1 test files, 180 assertions, 0 failures`
- PASS-line delta for this lane patch: `+60`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-overflow-pointermap-page-move-current-source-next111.php`

## Dependency Closure

No new support component is needed. The slice composes existing native PHP SQLite database image, freelist allocation, overflow page, and pointer-map primitives.

## Non-overlap

Avoided accepted batch106 write-apply stale-page admission, accepted table/index page relocation, root collapse, overflow freelist release, bulk overflow freeblocks, and B-tree overflow freepage/vacuum slices. This patch only handles current-source next111 overflow page relocation when the moved source page is a chained overflow page and the previous overflow page's next pointer must be rewritten.
