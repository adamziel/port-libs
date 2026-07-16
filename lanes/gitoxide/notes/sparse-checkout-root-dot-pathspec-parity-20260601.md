# Sparse Checkout Root-Dot Pathspec Parity - 2026-06-01

## Slice

Micro-slice `gitoxide-sparse-checkout-patternspec-parity-20260601T122629Z` ports one bounded `gix-pathspec` normalization edge into sparse checkout matching: pathspecs that normalize to repository root (`.` or a prefixed `../..`) must remain a root-dot pruning sentinel instead of becoming the empty nil matcher that includes every tree entry.

## Upstream Source Truth

- Pinned upstream Gitoxide commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-pathspec/src/pattern.rs` keeps normalized `Path::new(".")` as `path="."` and marks the pattern nil.
- `gix-pathspec/src/search/init.rs` computes the common prefix from the stored path text.
- `gix-pathspec/src/search/matching.rs` checks the common prefix before applying always-match behavior, so a root-dot pathspec does not match ordinary paths such as `index.php`; a prefixed `.` still expands under the caller prefix.

## Native Delta

- `SparseCheckoutSpec::normalizePathspecPath()` now tracks whether the user path contributed real `.` or `..` components. If those components consume the normalized path to repository root, the sparse pattern is stored as `.` instead of `''`.
- Empty nil pathspecs such as `:` and prefixed empty-search defaults still preserve their existing match-all or prefix-directory behavior.
- `SparseCheckoutTest` covers root `.` pathspecs, `:(top).` with a non-empty prefix, prefix-consuming `../..`, and a prefixed `.` that still materializes the current WordPress plugin subtree.
- `wordpress-sparse-checkout.php` exposes the same root-dot pruning and prefixed-dot expansion behavior for deployment selection smoke coverage.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `1 test files, 408 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9237 assertions, 0 failures`
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `No syntax errors detected`
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `No syntax errors detected`
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - `No syntax errors detected`
- Example smoke: `php -r '$example = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - `wordpress-sparse-checkout root-dot smoke ok`
- `git diff --check -- lanes/gitoxide`
  - passed with no output

## Non-Overlap

This does not repeat the accepted sparse-checkout prefix/default-search/absolute-root/wildmatch/directory-exclude/negative-wildcard/backslash/double-star/LF/POSIX/reversed-range/path-file-trimming slices. It reuses the tree pathspec root-dot parity source truth and applies the missing behavior only to sparse checkout pruning.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `SparseCheckoutSpec` and tree/pathspec traversal model; full upstream Cargo workspace execution remains out of scope for this isolated lane.
