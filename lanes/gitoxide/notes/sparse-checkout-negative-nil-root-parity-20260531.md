# Sparse Checkout Negative Nil Root Parity - 2026-05-31

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260531T221701Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/parse.rs`

`gix-pathspec::Search::pattern_matching_relative_path()` returns an `Always`
match for the empty repository-relative path before evaluating pathspec rules.
That means even an always-negative pathspec such as `:!` or `:(exclude)` does
not mark the checkout root as skipped, while descendants remain excluded.

## Implementation

- `SparseCheckoutSpec::matchesNonConePath()` now returns included for the empty
  path before applying non-cone pathspec rules.
- `SparseCheckoutTest.php` covers short and long always-negative pathspecs,
  positive-plus-negative ordering, descendant exclusion, and root tree-entry
  filtering.
- `examples/wordpress-sparse-checkout.php` records the WordPress deployment
  root sentinel behavior: the root remains materializable while plugin
  descendants are skipped by the negative nil pathspec.

## Verification

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; $s = PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs([":!"]); var_export([$s->includesPath("", true), $s->includesPath("", false), $s->skipWorktree("", true)]);'`
  reported `[false, false, true]`.
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 244 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 5926 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; ...'`
  reported `sparse checkout negative nil root example ok`.
- JSON validation for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json` and
  `lanes/gitoxide/lane-status.json` passed.
- `git diff --check -- lanes/gitoxide` passed.

## Non-Overlap

This does not repeat accepted sparse checkout cone rules, non-cone pattern-file
ordering, wildcard bracket/POSIX matching, backslash-byte matching, cwd prefix
normalization, absolute-root pathspec normalization, directory-only negative
pathspec authority, negative wildcard traversal, tree pathspec walking,
attributes/pathspec filtering, protocol, pack, object, reference, transport, or
merge behavior. The mapped behavior is limited to upstream `gix-pathspec` root
matching before always-negative pathspec evaluation.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
pathspec parser, sparse checkout matcher, and WordPress sparse checkout
example; it does not shell out to Git, run provider services, or require a new
shared support-library gate.
