# Gitoxide Tree Pathspec Empty Icase Prefix Parity

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T123211Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  joins an empty relative pathspec with the caller prefix during normalization.
  For non-directory patterns, the computed `prefix_len` stops at the parent
  directory, leaving the final caller-prefix component in the matchable path.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
  uses `pattern.prefix_len` as the common-prefix length for `ICASE` pathspecs.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  keeps that prefix directory case-sensitive, then applies folded matching to
  the rest of the normalized pattern.
- The related upstream tests are `gix-pathspec/tests/search/mod.rs`
  `prefixes_are_always_case_sensitive` and `common_prefix`, which establish
  that caller prefixes stay exact while the suffix can fold under `:(icase)`.

## Change

- `PathspecSearch::normalizePattern()` now normalizes explicit empty and nil
  prefixed pathspecs through the same prefix-component calculation used by
  non-empty relative pathspecs.
- `:(icase)` under caller prefix `WP-CONTENT/plugins` now has path
  `WP-CONTENT/plugins`, prefix directory `WP-CONTENT`, and common prefix
  `WP-CONTENT`. It matches `WP-CONTENT/plugins/...` and
  `WP-CONTENT/PLUGINS/...`, but still rejects `wp-content/plugins/...`.
- The WordPress tree pathspec example records the same deployment-selection
  boundary for mixed-case plugin directories.

Before the source change, the focused test failed because the PHP port kept the
entire caller prefix case-sensitive:

```text
FAIL empty icase pathspecs keep only parent prefixes case-sensitive during tree walks
Expected: 'WP-CONTENT'
Actual: 'WP-CONTENT/plugins'
```

## Verification

- `php -l lanes/gitoxide/src/PathspecSearch.php`
  passed.
- `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed.
- `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed: `1 test files, 338 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed: `3 test files, 1045 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; if (($out["emptyIcasePrefixDirectory"] ?? null) !== "WP-CONTENT" || ($out["emptyIcaseCommonPrefix"] ?? null) !== "WP-CONTENT" || ($out["emptyIcasePrefixContentPaths"] ?? null) !== ["WP-CONTENT/plugins/Safe.PHP", "WP-CONTENT/PLUGINS/SAFE.PHP"] || ($out["emptyIcaseFoldedFinalPrefixIncluded"] ?? null) !== true || ($out["emptyIcaseLowerRootSkipped"] ?? null) !== true) { fwrite(STDERR, "tree pathspec empty-icase example failed\n"); exit(1); } echo "tree pathspec empty-icase example ok\n";'`
  reported `tree pathspec empty-icase example ok`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed: `40 test files, 9305 assertions, 0 failures`.
- `git diff --check -- lanes/gitoxide`
  passed.

## Non-overlap

This extends the accepted tree/pathspec walk cluster without repeating prior
root-dot, parent-escape, absolute-root, empty-search, default search-mode,
negative-wildcard, newline wildmatch, malformed POSIX class, attributes
pathspec, sparse-checkout, transport, pack/index, reference transaction,
partial-clone, merge-base, or tree-merge slices.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local pathspec
parser/search normalizer, tree walker, PHP test harness, and existing WordPress
tree-pathspec example.
