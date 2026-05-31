# Gitoxide Index Checkout Cache-Tree Parity

Slice: `gitoxide-index-checkout-cache-tree-parity-20260531T101933Z`

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

## Upstream Source Truth

- `gix-index/src/init.rs`: `State::from_tree()` derives checkout index entries from tree leaves, preserving tree leaf paths, modes, object ids, and normal stage flags.
- `gix-index/tests/index/init.rs`: the `from_tree` fixture comparison asserts entry count, id, flags, mode, and path parity between tree-derived state and Git-created indexes.
- `gix-index/src/extension/tree/verify.rs`: `Tree::verify()` checks TREE cache root/child integrity and `verify_entries_count()` rejects impossible cache entry counts.
- `gix-index/src/extension/tree/decode.rs` and `write.rs`: TREE extension decode/write semantics remain the source of truth for the cache-tree payload.

No broad Cargo workspace run was attempted. The upstream cache is sparse/no-checkout and the full workspace build remains outside this VM slice. This handoff uses static targeted upstream source/test inventory plus native PHP verification.

## Native Behavior

`IndexFile::verifyCheckoutCacheTree()` now verifies both sides of checkout parity:

- the TREE extension still validates cache counts and object-backed tree children;
- parsed DIRC entries must match the tree-derived checkout entries by order, path, stage, mode, object id, assume-valid flag, and sparse `skip-worktree` flag;
- stale object ids, unmerged stages, extra entries, and missing sparse specs are rejected even when the cache tree itself is object-valid.

The WordPress index cache-tree example now passes the sparse checkout spec into verification so sparse deployments prove both TREE cache and checkout-entry parity.

## Evidence

- `php -l lanes/gitoxide/src/IndexFile.php`
- `php -l lanes/gitoxide/tests/IndexCacheTreeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-index-cache-tree.php`
- `php tools/run-tests.php lanes/gitoxide/tests/IndexCacheTreeTest.php`
  - `1 test files, 47 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `38 test files, 4091 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-index-cache-tree.php`
  - exited `0`
- `git diff --check -- lanes/gitoxide`
  - exited `0`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native tree parser, TREE cache extension parser/writer, sparse-checkout matcher, and index DIRC parser/writer.

## Non-Overlap

This does not repeat the accepted 2026-05-31 index/cache-tree round-trip or object-backed cache-tree child verification. It adds the missing checkout leaf-entry parity guard on top of that behavior.
