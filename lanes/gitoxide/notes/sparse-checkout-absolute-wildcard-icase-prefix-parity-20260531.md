# Sparse Checkout Absolute Wildcard Icase Prefix Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T120211Z`

Base accepted HEAD: `ab384a0d481bd4acef6592a38a3540df9d0cc3f2`

## Upstream Source Truth

- Upstream commit inspected: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-pathspec/tests/normalize/mod.rs` case `absolute_path_made_relative` records absolute wildcard pathspec normalization such as `/repo/*/b` with prefix directory `*` and `/repo/a/*/` with prefix directory `a/*`.
- `gix-pathspec/src/pattern.rs` computes `prefix_len` for absolute pathspecs from normalized path components, without dropping wildcard bytes from the prefix.
- `gix-pathspec/src/search/matching.rs` applies the normalized prefix as a byte-exact guard before icase matching the remainder of the pathspec.

## Native PHP Delta

- `SparseCheckoutSpec::fromPathspecs()` now keeps wildcard-containing absolute directory prefixes as case-sensitive prefixes when a pathspec uses inherited or explicit `icase`.
- Ordinary absolute wildcard pathspecs still glob real directory names when not case-folded.
- `examples/wordpress-sparse-checkout.php` now exposes the deployment-root distinction between an icase absolute wildcard pathspec that only matches a literal `*` directory and an ordinary absolute wildcard pathspec that matches a real WordPress content directory.

## Verification

- Red-first precheck before implementation:
  - `SparseCheckoutSpec::fromPathspecs([":(icase)$root/*/readme.md"], root: $root)` incorrectly included `wp-content/README.md` and `WP-CONTENT/README.md`.
- Syntax:
  - `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - Result: no syntax errors detected in all three changed PHP files.
- Focused sparse checkout: `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `1 test files, 174 assertions, 0 failures`.
- Related pathspec/tree guard: `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - Result: `2 test files, 258 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php lanes/gitoxide/tests`
  - Result: `39 test files, 4460 assertions, 0 failures`.
- Example smoke:
  - `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  - Result: `sparse absolute wildcard example ok`.
- Whitespace check: `git diff --check -- lanes/gitoxide`
  - Result: no output, exit 0.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP path normalization and regex-based sparse pathspec matching; no upstream binary, live Git provider, credential store, or shared support-library activation gate is required.

## Non-Overlap

This extends accepted sparse checkout/pathspec work without repeating cone rules, non-cone pattern-file ordering, wildcard bracket/POSIX matching, directory-only excludes, cwd prefix normalization, absolute-root normalization without wildcard prefixes, default pathspec search modes, tree pathspec walking, attributes/pathspec filters, protocol, pack, object, or reference behavior. The new behavior is limited to upstream `gix-pathspec` absolute wildcard prefix preservation under `icase`.
