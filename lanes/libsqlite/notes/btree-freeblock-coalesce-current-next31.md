# B-tree Freeblock Coalesce Current/Next31

## Behavior

Adds `SQLiteBTreeFreeblockCoalescePlan`, a bounded page-image helper for
SQLite b-tree pages where overflow-backed deletes leave current/next
freeblocks separated by 1-3 fragmented bytes. The plan merges those fragments
into the preceding freeblock, rewrites the freeblock chain, reduces the header
fragmented-byte count, and optionally clears the merged freeblock bytes for
secure-delete style diagnostics.

## Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockCoalesceCurrentNext31Test.php
```

Result: `1 test files, 54 assertions, 0 failures`.

```sh
php lanes/libsqlite/examples/application-btree-freeblock-coalesce-current-next31.php
```

Result: JSON smoke reports copied `wp_options` overflow-backed delete page
coalescing current/next freeblocks, reducing fragmented bytes from `6` to `2`,
and rewriting three freeblocks into one reusable freeblock without ext/sqlite.

## Non-Overlap

This avoids accepted overflow freelist release, bulk overflow freeblocks,
root-collapse/page-move/index-interior merge, VFS writer/sync/lock, WAL
rollback/checkpoint/savepoint, JSON table, SELECT SQL, and Unicode GLOB
clusters. The new behavior is the current page-image coalescing step for
current/next b-tree freeblocks after overflow-backed deletes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
b-tree page-header/freeblock parsing and page-image rewriting primitives.
