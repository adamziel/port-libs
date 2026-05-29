# B-tree Overflow Freelist Rootpage Current Source Next126

This slice adds `SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan`, a bounded current-source transition for the SQLite path where obsolete overflow pages from a deleted WordPress-sized option payload are released to the freelist, then one of those pages is immediately reused as a new schema root page.

The behavior is intentionally narrower than earlier overflow/freelist reuse work: it records the schema-rootpage handoff and asserts that auto-vacuum pointer-map ownership moves from `first-overflow-page` to `free-page` to `root-page` with parent `0`, while the unreused overflow page remains on the freelist.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowFreelistRootpageCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowFreelistRootpageCurrentSourceNext126Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-overflow-freelist-rootpage-current-source-next126.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreelistRootpageCurrentSourceNext126Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-overflow-freelist-rootpage-current-source-next126.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted overflow freelist release, bulk overflow freeblocks, overflow vacuum reuse next104/121, root collapse, page move, index-interior merge, freelist trunk pointer-map reuse, and delete overflow vacuum pointer-map next119. The new surface is rootpage allocation from a released overflow freelist page and schema-rootpage evidence for current-source next126.

Dependency closure: no new support component is needed; this reuses existing native PHP database page, overflow-chain, freelist free/allocation, pointer-map, table leaf, and record helpers.
