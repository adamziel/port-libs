# Sparse Checkout Prefix Pathspec Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T092824Z`

Base accepted HEAD: `505e973c7fba58525b7fffcb767bf99390508892`

## Upstream Source Truth

- `gix-pathspec/src/pattern.rs`: `Pattern::normalize()` prepends the current worktree prefix for non-`top` pathspecs, resolves `.` and `..`, rejects paths that leave the worktree, and records a `prefix_len`.
- `gix-pathspec/src/search/init.rs`: `common_prefix_len()` uses `prefix_len` for `icase` pathspecs so the cwd prefix remains case-sensitive.
- `gix-pathspec/src/search/matching.rs`: matching and directory pruning compare the normalized common prefix before applying pathspec `icase` behavior to the remaining path.

## Native PHP Delta

- `SparseCheckoutSpec::fromPathspecs()` now accepts an optional repository-relative `$prefix`, normalizes non-top pathspecs against it, resolves `..`, and rejects traversal above the repository root.
- Prefixed sparse pathspecs preserve the normalized cwd prefix as a case-sensitive guard, even when the pathspec uses `:(icase)`.
- Empty pathspec lists with a prefix now materialize that prefix subtree, while explicit nil pathspec `:` continues to match everything.
- `examples/wordpress-sparse-checkout.php` now records prefixed plugin deployment pathspec behavior for root-level top magic, nested path-aware glob pruning, and case-sensitive prefix rejection.

## Verification

- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 112 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 163 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `38 test files, 3817 assertions, 0 failures`.
- Syntax: `php -l lanes/gitoxide/src/SparseCheckoutSpec.php && php -l lanes/gitoxide/tests/SparseCheckoutTest.php && php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Example smoke: `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse prefix example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP path normalization, byte-string prefix comparison, and sparse pathspec regex matching; no shell-out, live Git provider, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends the accepted sparse checkout/pathspec coverage without repeating bracket/range/POSIX wildmatch, authoritative excludes, attributes/pathspec state filters, tree pathspec walking, protocol v2, pack delta, loose object integrity, or reference peeling behavior. The new behavior is limited to upstream `gix-pathspec` prefix normalization and case-sensitive cwd-prefix matching for sparse checkout decisions.
