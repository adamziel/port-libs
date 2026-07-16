# Sparse Checkout Absolute Root Pathspec Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T095816Z`

Base accepted HEAD: `633d868181ed471ba314711c0ee3aff27a79b97e`

## Upstream Source Truth

- `gix-pathspec/src/pattern.rs`: `Pattern::normalize()` makes absolute pathspecs relative to the worktree root, rejects absolute paths outside the root, and rejects relative normalization that leaves the worktree.
- `gix-pathspec/tests/normalize/mod.rs`: focused cases cover `/repo/a`, `/repo/a/..//.///b`, `:(top)/a/b`, absolute paths outside the worktree, and paths that break out after root stripping.
- `gix-pathspec/tests/search/mod.rs`: `prefixes_are_always_case_sensitive` keeps normalized prefix directories case-sensitive even when the pathspec itself uses `icase`.

## Native PHP Delta

- `SparseCheckoutSpec::fromPathspecs()` now accepts an optional `$root` argument for repository/worktree absolute pathspec normalization.
- Absolute pathspecs inside `$root` are stripped to repository-relative sparse paths before matching.
- Absolute pathspecs outside `$root`, roots that are not absolute, and paths that leave the worktree after root stripping now raise `InvalidArgumentException`.
- Directory prefixes derived from absolute pathspecs remain case-sensitive before `icase` matching is applied to the remaining pattern bytes.
- `examples/wordpress-sparse-checkout.php` now records deployment-root absolute pathspecs for a plugin block file, case-insensitive readme selection, uppercase-prefix rejection, and build directory exclusion.

## Verification

- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 130 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 203 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `38 test files, 4016 assertions, 0 failures`.
- Example smoke: `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse absolute root example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP path normalization and sparse pathspec matching; no upstream binary, live Git provider, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends the accepted sparse checkout/pathspec slices without repeating cone rules, non-cone include/exclude ordering, wildmatch bracket/range/POSIX support, cwd prefix normalization, tree pathspec walking, attributes/pathspec filters, protocol v2, pack delta guards, loose object integrity, or reference peeling behavior. The new behavior is limited to upstream `gix-pathspec` absolute-root normalization and case-sensitive directory-prefix matching for sparse checkout decisions.
