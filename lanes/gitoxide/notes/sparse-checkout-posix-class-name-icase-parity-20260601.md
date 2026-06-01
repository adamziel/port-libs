# Sparse Checkout POSIX Class-Name Icase Parity - 2026-06-01

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T145044Z`

## Source Truth

- Pinned upstream Gitoxide commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  lowercases pattern bytes while `Mode::IGNORE_CASE` is active before
  evaluating bracket expressions.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  passes folded matching for `:(icase)` pathspecs.
- Existing accepted attributes/pathspec parity already proved the same POSIX
  class-name folding boundary for pathspec search. This slice applies the
  missing sparse-checkout side.

## Native Delta

- `SparseCheckoutSpec::characterClassRegex()` now folds POSIX class names before
  lookup when the pathspec rule is case-insensitive.
- Supported uppercase class names such as `[[:UPPER:]]` now match sparse
  checkout pathspecs under `:(icase)`.
- Unsupported uppercase class names still abort wildcard matching and fall back
  to verbatim pathspec matching, preserving the existing unknown-class boundary.
- `wordpress-sparse-checkout.php` exposes the WordPress upload/plugin selection
  smoke path for folded class-name matching and unknown-class fallback.

## Verification

- Before this change, a focused probe returned `false` for
  `SparseCheckoutSpec::fromPathspecs([":(glob,icase)wp-content/uploads/[[:UPPER:]]LUGINS/**"])->includesPath("wp-content/uploads/plugins/block.json", false)`.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  - before: `1 test files, 426 assertions, 0 failures`
  - after: `1 test files, 438 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 9863 assertions, 0 failures`
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`
  - passed
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`
  - passed
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  - passed
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecIcaseUpperPosixClassNameIncluded", "pathspecIcaseUpperPosixClassNameDirectoryIncluded", "pathspecIcaseUnknownPosixClassNameFallbackIncluded", "pathspecIcaseUnknownPosixClassNameWildcardSkipped"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse checkout POSIX class-name icase example ok\n";'`
  - `sparse checkout POSIX class-name icase example ok`
- `git diff --check -- lanes/gitoxide`
  - passed with no output

## Non-Overlap

This deepens sparse-checkout pathspec parity without repeating accepted
directory-only excludes, negative nil roots, default/environment pathspec
modes, absolute-root and absolute-backslash normalization, escaped-byte
traversal, LF-byte wildmatch, reversed ranges, unknown POSIX class fallback,
malformed POSIX class resume behavior, pattern-file trimming, double-star
component boundaries, tree pathspec walking, attributes/pathspec filtering, or
object/transport/reference/merge behavior.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`SparseCheckoutSpec`, PCRE-backed wildcard translation, the existing WordPress
sparse-checkout example, the PHP test harness, and the hydrated upstream
Gitoxide cache for source-truth inspection. It does not shell out to Git, run
live provider tests, inspect credentials, or require a shared support-library
activation gate.
