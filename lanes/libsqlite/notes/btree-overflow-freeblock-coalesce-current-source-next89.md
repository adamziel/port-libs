# B-tree overflow freeblock coalesce current-source next89

This slice adds `SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan`.
It materializes one delete step that coalesces current/next leaf freeblock
fragments and releases the same row's obsolete overflow pages into the full
freelist with auto-vacuum pointer-map entries rewritten to free-page.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNext89Test.php`
  - `1 test files, 152 assertions, 0 failures`
  - 72 focused PASS lines

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-btree-overflow-freeblock-coalesce-current-source-next89.php`
  - emits copied `wp_options` overflow-backed transient delete evidence showing
    leaf freeblock coalescing, secure-delete clearing, freelist trunk/leaf
    materialization, and pointer-map free-page rewrites without `ext/sqlite`.

Non-overlap:

- Avoids accepted standalone freeblock coalescing, bulk overflow freeblocks,
  overflow freelist release, overflow freeblock truncate current-source next87,
  delete overflow materialization, page relocation, root collapse,
  index-interior merge, VFS writer/sync/lock/rollback clusters, WAL
  byte/checkpoint clusters, JSON table source/cursor/constraint clusters,
  SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior.
  The new surface is a combined current-source delete materialization where
  the same operation rewrites both the leaf freeblock chain and the obsolete
  overflow freelist/pointer-map state.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  SQLite page-image, B-tree freeblock, overflow, freelist, and pointer-map
  primitives.
