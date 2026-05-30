# B-tree Overflow Root Redistribute Current/Next 38

This slice adds `SQLiteBTreeOverflowRootRedistributePlan`, a bounded native PHP
composition for deleting an overflow-backed row from the current table leaf
under a root interior page, redistributing cells with the next sibling, and
then releasing the obsolete overflow pages into the freelist with auto-vacuum
pointer-map updates.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRootRedistributeCurrentNext38Test.php`
- Result: `1 test files, 367 assertions, 0 failures` with 52 PASS cases
- `php lanes/libsqlite/examples/application-btree-overflow-root-redistribute-current-next38.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowRootRedistributePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowRootRedistributeCurrentNext38Test.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-root-redistribute-current-next38.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this avoids accepted root-collapse, page relocation, index-interior
merge, bulk overflow freeblocks, overflow freelist release, B-tree freeblock
coalescing, WAL/VFS writer/sync/rollback clusters, JSON table cursor/source/
constraint clusters, SELECT SQL text/subquery/group/order clusters, Unicode
GLOB, and batch23/batch31 metadata work. The new behavior is the composed
current/next root-adjacent redistribution where obsolete overflow pages are
released in the same plan after the leaf redistribution has rewritten root
divider state.

Dependency closure: no new support component is needed; this reuses lane-local
B-tree table leaf deletion, table leaf balance application, freelist free
planning, secure-delete clearing, and auto-vacuum pointer-map mutation.
