# Sparse Checkout Directory Type Boundary Parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T150641Z`

Base accepted HEAD: `5042ee5a640251937d88ffe1e25c7b681010f72f`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: `pattern_matching_relative_path()` treats `is_dir: None` as `false`, so `MUST_BE_DIR` exact matches require a known directory while descendant prefix matches remain possible after the pathspec directory component.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`: `simplified_search_respects_must_be_dir`, `no_pathspecs_respect_prefix`, and `directory_matches_prefix` cover file-like unknown directory types and directory-component prefix pruning instead of raw string-prefix pruning.

## Native PHP Delta

- `SparseCheckoutSpec::nonConeRuleMatches()` now rejects exact directory-only pathspec matches when the caller passes `null` for the path type, matching Gitoxide's `None -> false` boundary.
- `SparseCheckoutSpec::pathspecRuleCanMatchDescendant()` now uses a directory-component prefix check, so a `cache/` pathspec can keep `cache/page.html` reachable without keeping `cache-busting` alive.
- `examples/wordpress-sparse-checkout.php` now exposes unknown-type directory-only cache selection and prefixed empty pathspec behavior for WordPress deployment sparse checkouts.

## Red-First Evidence

- Before the fix, `SparseCheckoutSpec::fromPathspecs(["wp-content/cache/"])->includesPath("wp-content/cache", null)` returned `true`.
- The first implementation pass exposed the adjacent raw-prefix bug: `wp-content/cache/` still kept sibling directory `wp-content/cache-busting` reachable. The final patch fixes both edges in the same upstream `MUST_BE_DIR` and directory-prefix cluster.

## Verification

- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 200 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 310 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 4752 assertions, 0 failures`.
- Example smoke:
  - `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse directory type example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing lane-local sparse checkout matcher, pathspec parser, tree filtering, and PHP test harness. It does not shell out to Git, run live services, or require a shared support-library activation gate.

## Non-Overlap

This does not repeat accepted sparse-checkout prefix normalization, directory-only exclude authority, wildcard/bracket/POSIX pathspec matching, default search modes, absolute-root normalization, absolute wildcard icase prefix handling, or sparse POSIX class fallback. It is bounded to upstream `gix-pathspec` unknown directory-type handling for `MUST_BE_DIR` exact matches and directory-component pruning for sparse checkout descendants.
