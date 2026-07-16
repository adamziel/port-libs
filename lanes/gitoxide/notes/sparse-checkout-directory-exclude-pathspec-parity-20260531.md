# Sparse Checkout Directory Exclude Pathspec Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T103458Z`

Base accepted HEAD: `1681be96b403cae039655fef5cb4703982266b2d`

## Upstream Source Truth

- Upstream commit inspected: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-pathspec/tests/search/mod.rs` case `included_directory_and_excluded_subdir_top_level_with_prefix` asserts that `:/foo` includes `foo` and `foo/bar`, while `:!/foo/target/` excludes the `foo/target` directory and all children but still allows a file named `foo/target`.
- `gix-pathspec/src/search/init.rs` orders excluded pathspecs before positive pathspecs, and `gix-pathspec/src/search/matching.rs` keeps directory-only exclusion matches authoritative instead of allowing a positive prefix traversal check to resurrect the excluded directory.

## Native PHP Delta

- `SparseCheckoutSpec::matchesNonConePath()` now records matched negative pathspec rules and returns excluded before the directory descendant fallback can re-include the same directory.
- The guard is limited to pathspec-origin rules so ordinary non-cone sparse pattern-file last-match ordering remains unchanged.
- `SparseCheckoutTest.php` adds the upstream-shaped `:/foo` plus `:!/foo/target/` prefix case and a WordPress `wp-content` deployment case where `wp-content/cache/` is excluded as a directory while a file named `cache` and sibling `cache-busting` paths remain included.
- `examples/wordpress-sparse-checkout.php` now exposes the directory-only cache exclusion, descendant skip-worktree decision, sibling inclusion, and filtered tree materialization.

## Verification

- Red-first precheck before implementation:
  - `SparseCheckoutSpec::fromPathspecs([":/foo", ":!/foo/target/"], prefix: "foo")->includesPath("foo/target", true)` returned `true`, incorrectly re-including the excluded directory through prefix traversal.
- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 144 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 223 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `38 test files, 4197 assertions, 0 failures`.
- Example smoke: `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse directory-only exclude example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP pathspec parsing, sparse checkout matching, and tree-entry filtering; no upstream binary, live Git provider, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends accepted sparse checkout/pathspec work without repeating cone rules, non-cone pattern-file ordering, wildcard bracket/POSIX matching, cwd prefix normalization, absolute-root normalization, tree pathspec walking, attributes/pathspec filters, protocol, pack, object, or reference behavior. The new behavior is limited to upstream `gix-pathspec` directory-only negative pathspec authority during sparse checkout traversal.
