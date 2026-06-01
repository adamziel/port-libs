# Sparse Checkout Index Mode Parity

Source truth:

- Upstream `gix-index/src/access/sparse.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.

Implemented behavior:

- Added native `SparseCheckoutOptions::sparseMode()` parity for upstream `Options::sparse_mode()`.
- `sparse_checkout=false` always maps to `Disabled`, regardless of cone or sparse-index flags.
- Cone sparse checkout with `index.sparse=true` maps to directory entries plus included entries.
- Cone sparse checkout with `index.sparse=false` stores all entries and skips unmatched paths.
- Non-cone sparse checkout stores all entries and applies ignore-pattern skip-worktree behavior, regardless of sparse-index writing.

Focused verification:

- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php` passed `1 test files, 446 assertions, 0 failures`.
- `php -r '... require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'` passed with `wordpress sparse checkout index mode smoke ok`.

Dependency closure:

- No new support component is required. The slice reuses existing sparse checkout/index concepts and adds a small PHP value object for the upstream option-to-mode derivation.

Non-overlap:

- Does not change accepted pathspec parsing, wildcard matching, root-dot sentinel behavior, sparse pattern-file trimming, index cache-tree writing, or skip-worktree entry generation.
