# Sparse checkout dangling-backslash pathspec parity

Slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T134328Z`

Base accepted HEAD: `9cec814218deb6c90aaec05ae00c825ef24541da`

## Source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  returns `NoMatch` when a wildcard pattern ends with an unpaired backslash.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  tries wildmatch first for shell/path-aware glob pathspecs and then calls
  `match_verbatim()` when wildcard matching fails.
- The lane's `PathspecSearch` already carried that parity in
  `PathspecTreeWalkTest.php` under `falls back to verbatim matches after
  dangling backslash wildmatch aborts`.

## Change

- `SparseCheckoutSpec::globRegex()` now aborts regex construction for a
  dangling escape and lets sparse checkout pathspec mode fall back to verbatim
  matching.
- `SparseCheckoutTest.php` now covers exact literal trailing-backslash matches,
  non-expansion of wildcard-plus-dangling-backslash patterns, shell-default
  fallback, ancestor traversal, and tree materialization.
- `wordpress-sparse-checkout.php` now includes a deployment-shaped example for
  sparse checkout pathspec filters that must not expand a dangling escape.

## Evidence

Before this slice, focused sparse checkout verification passed at:

```text
php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php
1 test files, 408 assertions, 0 failures
```

After the fix:

```text
php -l lanes/gitoxide/src/SparseCheckoutSpec.php
No syntax errors detected in lanes/gitoxide/src/SparseCheckoutSpec.php

php -l lanes/gitoxide/tests/SparseCheckoutTest.php
No syntax errors detected in lanes/gitoxide/tests/SparseCheckoutTest.php

php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php
No syntax errors detected in lanes/gitoxide/examples/wordpress-sparse-checkout.php

php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php
1 test files, 426 assertions, 0 failures

php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php
2 test files, 764 assertions, 0 failures

php -r '<wordpress sparse dangling-backslash example smoke>'
sparse dangling backslash example ok

php tools/run-tests.php lanes/gitoxide/tests
40 test files, 9644 assertions, 0 failures
```

Expected lane-status movement: `phpPass` 9626 -> 9644 and mapped coverage
1797 -> 1798 / 2886 for one additional conservative sparse-checkout pathspec
parity row.

## Dependency closure

No new support component is needed. This reuses the existing native
`SparseCheckoutSpec`, `PathspecSearch`, `Tree`, and `TreeEntry` support.

## Non-overlap

This is additive to accepted sparse-checkout pathspec coverage for directory
excludes, negative wildcard traversal, absolute-root normalization, absolute
backslash bytes, escaped-byte traversal, LF-byte shell wildmatch, POSIX class
fallback/resume, reversed ranges, double-star boundaries, and absolute wildcard
prefix handling. It does not touch protocol/transport, pack/index, references,
partial clone, merge-base, tree-merge, credentials, config include, or URL/
refspec behavior.
