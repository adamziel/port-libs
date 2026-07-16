# Gitoxide Index Checkout Cache-Tree Component Validation

Slice: `gitoxide-index-checkout-cache-tree-parity-20260531T105434Z`

Base accepted HEAD: `229ee6ac6ba54ebcac89b65db02638641eecef2d`

## Upstream Source Truth

- `gix-index/src/init.rs`: `State::from_tree()` validates every tree path
  component before adding checkout index entries.
- `gix-index/tests/index/init.rs`: `from_tree_validation` rejects tree entries
  whose components contain literal `/` or `\` separators.
- `gix-index/tests/index/init.rs`:
  `from_tree_returns_file_directory_conflicts_until_fixed` records the current
  upstream behavior where a file/directory conflict still yields `a`,
  `a/post-checkout`, and `payload` index paths until the upstream issue is
  fixed.
- `gix-validate/src/path.rs` and `gix-validate/tests/validate/path.rs`: the
  mapped component error is `Path separators like / or \ are not allowed`.

No broad Cargo workspace run was attempted. This uses the existing local
upstream cache as static source truth plus focused native PHP verification.

## Native Behavior

`TreeEntry::assertValidPathComponent()` now gives checkout/index builders a
shared component validator without changing raw tree-object parsing.

`IndexFile::entriesForCheckout()` and `IndexCacheTree::fromTree()` both reject
tree entry names containing literal slash or backslash components before they
can be flattened into misleading checkout paths or TREE cache nodes. The slice
also preserves the upstream-current file/directory conflict shape so the PHP
port does not silently "fix" behavior that upstream currently exposes.

The WordPress index cache-tree example now records rejection of a malicious
`../wp-config.php` tree component before deployment index generation.

## Evidence

- Red-first: `php tools/run-tests.php lanes/gitoxide/tests/IndexCacheTreeTest.php`
  - before source changes: `1 test files, 51 assertions, 1 failures`
- `php -l lanes/gitoxide/src/TreeEntry.php`
- `php -l lanes/gitoxide/src/IndexCacheTree.php`
- `php -l lanes/gitoxide/src/IndexFile.php`
- `php -l lanes/gitoxide/tests/IndexCacheTreeTest.php`
- `php -l lanes/gitoxide/examples/wordpress-index-cache-tree.php`
- `php tools/run-tests.php lanes/gitoxide/tests/IndexCacheTreeTest.php lanes/gitoxide/tests/TreeTest.php`
  - `2 test files, 81 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4290 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-index-cache-tree.php`
  - exited `0`
- `git diff --check -- lanes/gitoxide`
  - exited `0`

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native tree,
index, cache-tree, sparse-checkout, and PHP byte-string validation primitives.

## Non-Overlap

This does not repeat accepted cache-tree round-trip, object-backed child
verification, sparse flag, or checkout leaf parity behavior. It adds the
remaining upstream-backed `from_tree` path-component validation edge and keeps
the current upstream file/directory conflict shape documented in tests.
