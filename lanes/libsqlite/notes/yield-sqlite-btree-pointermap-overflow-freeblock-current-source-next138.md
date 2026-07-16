# B-tree Pointer-Map Overflow Freeblock Current Source Next138

## Behavior

Adds `SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNext138Plan`, a bounded
current-source B-tree transition plan for auto-vacuum databases. The plan
captures the original overflow-chain next pointers and pointer-map ownership
before leaf freeblock coalescing, obsolete overflow release, freelist insertion,
and replacement overflow allocation. It then reports the transition from the
current source chain into the rewritten replacement chain so page reuse cannot
accidentally use post-free page images as the source of truth.

The focused Application path models deleting and replacing a large `wp_options`
autoload value whose current overflow chain is freed through a fragmented leaf
page and immediately reused for the replacement value.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNext138Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 265 assertions, 0 failures
PASS_LINES=89
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-pointermap-overflow-freeblock-current-source-next138.php
```

Expected key output:

```json
{
    "action": "btree-pointermap-overflow-freeblock-current-source-next138",
    "current_source_pages": [5, 6],
    "current_source_next_pages": [6, 0],
    "allocated_pages": [6, 5],
    "transition_replacement_next_pages": [5, 0],
    "final_freelist_pages": []
}
```

## Non-Overlap

This slice does not repeat accepted next128/next132/next133/next134 B-tree
freeblock, freelist, pointer-map, vacuum, or rebalance summaries. The new
surface is the current-source overflow chain snapshot carried through
freeblock coalescing and immediate reuse, proving the replacement chain is
built from current-source next pointers before the pages are freed and
overwritten.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
SQLite page, pointer-map, overflow-page, freeblock-coalesce, freelist-release,
and freelist-allocation primitives.
