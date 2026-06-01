# Sparse Checkout Reversed Range Pathspec Parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T024445Z`

Base accepted HEAD: `c1c883c28f62d04121f13200bac2177a47c69bd4`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  evaluates bracket ranges byte-by-byte. Reversed ranges such as `[z-a]`
  still match the starting byte literally, while the consumed endpoint does
  not become a literal match.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  sends sparse/pathspec wildmatches through gix-glob with path-aware slash
  handling and ASCII case folding when `:(icase)` is present.

## Native PHP Delta

- `SparseCheckoutSpec::characterClassRegex()` now mirrors the range-tail logic
  used by the existing native `GitAttributes` and `PathspecSearch` matchers,
  avoiding invalid PCRE ranges for sparse-checkout pathspecs.
- Case-sensitive reversed ranges keep Gitoxide's start-byte-only behavior,
  negated reversed ranges become the corresponding negated start-byte class,
  and `:(icase)` alphabetic reversed ranges normalize to a forward ASCII
  range.
- `SparseCheckoutTest.php` adds focused sparse-checkout assertions for
  positive, negated, and icase reversed ranges with no `preg_match()` warnings.
- `wordpress-sparse-checkout.php` exposes the same upload-directory sparse
  deployment behavior as the local example smoke.

## Red-First Evidence

Before the patch:

```sh
php -d display_errors=1 -r 'require "tools/bootstrap.php"; set_error_handler(function($errno,$message){ if (str_contains($message, "preg_match()")) { echo "WARNING:$message\n"; return true; } return false; }); $s = PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs([":(glob)wp-content/uploads/[z-a]/**"]); var_export([$s->includesPath("wp-content/uploads/z/photo.jpg", false), $s->includesPath("wp-content/uploads/m/photo.jpg", false)]); restore_error_handler(); echo "\n";'
```

reported two `preg_match(): Compilation failed: range out of order in character class` warnings and returned `[false, false]`.

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php && php -l lanes/gitoxide/tests/SparseCheckoutTest.php && php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 313 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `2 test files, 506 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7024
  assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecReversedRangeStartIncluded", "pathspecReversedRangeMiddleSkipped", "pathspecNegatedReversedRangeStartSkipped", "pathspecNegatedReversedRangeMiddleIncluded", "pathspecIcaseReversedRangeMiddleIncluded", "pathspecIcaseReversedRangeDigitSkipped"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse reversed range example ok\n";'`
  reported `sparse reversed range example ok`.
- `git diff --check -- lanes/gitoxide` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This extends sparse-checkout pathspec parity without repeating cone rules,
absolute/backslash pathspec normalization, POSIX class fallback, double-star
component boundaries, negative wildcard traversal, directory-only exclusions,
tree pathspec walking, attributes/pathspec reversed ranges, transport,
protocol, pack/object, reference, or merge behavior. The mapped behavior is
limited to gix-glob reversed bracket ranges as used through sparse-checkout
pathspec matching.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local PHP
sparse-checkout pathspec parser, PCRE-backed wildmatch translator, tree
filtering, WordPress example, and PHP test harness. It does not shell out to
Git, run live provider tests, read credentials, or require a shared support
activation gate.
