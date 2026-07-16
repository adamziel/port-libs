# Sparse checkout pathspec common-prefix parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T173329Z`

Base: `9b7f72e7da02721a548034c2c01c4d151fbb5234`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`

`gix-pathspec::Search` computes a case-sensitive common prefix from positive
pathspecs only, ignores excludes for the inclusive pruning prefix, uses the
caller prefix as the case-sensitive prefix directory, and returns a longest
common directory when that directory is known from the normalized positive
patterns. `gix-glob` treats `*`, `?`, `[`, and `\` as wildcard-prefix
boundaries for this pruning calculation.

## Behavior Ported

- `SparseCheckoutSpec::pathspecCommonPrefix()` now exposes the upstream-style
  inclusive common prefix for pathspec-backed sparse matchers.
- `SparseCheckoutSpec::pathspecPrefixDirectory()` exposes the normalized caller
  prefix directory, preserving the upstream rule that this portion remains
  case-sensitive even under `:(icase)`.
- `SparseCheckoutSpec::pathspecLongestCommonDirectory()` maps the upstream
  positive-pattern longest common directory behavior and returns `null` for
  all-exclude or non-directory-only prefixes without a directory boundary.
- Backslash pathspec bytes now stop common-prefix expansion the same way
  upstream `gix-glob` computes `first_wildcard_pos`, while existing matching
  still handles them through the sparse pathspec wildmatch/verbatim fallback.
- `wordpress-sparse-checkout.php` records a WordPress plugin deployment case
  where `src/` and `build/` share a sparse traversal prefix while a private
  build subtree remains excluded.

Red-first probe on this base:

```sh
php -r 'require "tools/bootstrap.php"; echo method_exists("PortLibs\\Gitoxide\\SparseCheckoutSpec", "pathspecCommonPrefix") ? "yes\n" : "no\n";'
```

Result before implementation: `no`.

## Verification

```sh
php -l lanes/gitoxide/src/SparseCheckoutSpec.php
php -l lanes/gitoxide/tests/SparseCheckoutTest.php
php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php
```

Result: no syntax errors.

```sh
php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php
```

Result: `1 test files, 473 assertions, 0 failures`.

```sh
php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php lanes/gitoxide/tests/AttributesPathspecTest.php
```

Result: `3 test files, 1212 assertions, 0 failures`.

```sh
php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecCommonPrefix" => "wp-content/plugins/gutenberg/", "pathspecPrefixDirectory" => "wp-content", "pathspecLongestCommonDirectory" => "wp-content/plugins/gutenberg/", "pathspecCommonPrefixPrivateBuildSkipped" => true] as $key => $expected) { if (($out[$key] ?? null) !== $expected) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse pathspec common prefix example ok\n";'
```

Result: `sparse pathspec common prefix example ok`.

```sh
git diff --check -- lanes/gitoxide
```

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted sparse checkout cone rules, non-cone pattern-file
trimming, negative nil, directory-only excludes, negative wildcard traversal,
LF-byte wildmatch, double-star component matching, POSIX class fallback,
absolute-root/backslash normalization, root-dot handling, tree pathspec walks,
attribute pathspec filters, pack/index, object database, protocol, or reference
transaction work. It is bounded to `gix-pathspec` search-prefix pruning
metadata for sparse pathspec matchers.

## Dependency Closure

No new support component is needed. This reuses the lane-local native PHP
pathspec parser, sparse matcher, tree-entry filtering model, test harness, and
WordPress sparse-checkout example. Full upstream Cargo workspace execution
remains out of scope for this isolated slice.
