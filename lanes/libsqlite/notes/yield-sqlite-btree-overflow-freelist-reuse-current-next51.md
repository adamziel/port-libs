# yield-sqlite-btree-overflow-freelist-reuse-current-next51

This slice adds `SQLiteOverflowFreelistReusePlan`, a bounded current-source
B-tree behavior for deleting overflow-backed `wp_options` table/index cells and
immediately allocating the replacement overflow chain from the resulting
current/next freelist image.

It is intentionally distinct from accepted overflow freelist release, bulk
overflow freeblock materialization, root collapse, page relocation, and interior
freelist rebalance work. The new behavior verifies the transition from
obsolete overflow pages to freelist trunk/leaves and then back to
first-overflow/overflow pointer-map entries for the replacement chain.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteOverflowFreelistReuseCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS overflow freelist reuse current next51 reuses freshly released pages
PASS overflow freelist reuse current next51 can append after reusing released pages
PASS overflow freelist reuse current next51 rejects replacement without append capacity
PASS overflow freelist reuse current next51 validates replacement count

1 test files, 69 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-overflow-freelist-reuse-current-next51.php
```

The smoke reports copied `wp_options` transient table and option-name index
overflow pages `[20, 21, 22, 106, 107]` being released, then reused as the
replacement overflow chain `[21, 107, 106, 22, 20]` with auto-vacuum pointer-map
parents rewritten to the new chain.

Dashboard delta: +4 focused `TestRunner` PASS cases and 69 assertions. Update
`phpPass` from 18565 to 18569 for this lane-local test growth.

Dependency closure: no new support component is needed. The patch reuses
existing native PHP freelist release, freelist allocation, auto-vacuum
pointer-map, and SQLite database page-image primitives.
