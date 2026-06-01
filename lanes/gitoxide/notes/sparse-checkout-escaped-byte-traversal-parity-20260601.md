# Sparse checkout escaped byte traversal parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T035605Z`

Base accepted HEAD: `bf75a27f708d456a2f08c9c540bce1189ab451a6`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/parse.rs`
  defines `\` as a glob metacharacter in `GLOB_CHARACTERS`, so an escaped byte
  participates in first-wildcard/prefix calculations.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  uses that first wildcard position in `can_match_relative_path()` before
  full matching. A pathspec such as `f\oo/block.json` can conservatively keep
  `f...` directories traversable, then match `foo/block.json` by escaped-byte
  wildmatch and `f\oo/block.json` by verbatim fallback.

## Native PHP delta

- `SparseCheckoutSpec::pathspecRuleHasActiveWildcard()` now treats `\` as an
  active pathspec traversal boundary instead of skipping over the escaped byte.
- Sparse checkout no longer prunes directories such as `wp-content/plugins/f`
  and `wp-content/plugins/foo` before discovering an escaped-byte match.
- `SparseCheckoutTest.php` covers ordinary escaped bytes, escaped slashes,
  verbatim fallback, and tree-entry materialization for the conservative
  traversal prefix.
- `wordpress-sparse-checkout.php` exposes the same WordPress plugin deployment
  pathspec decisions for example smoke verification.

## Red-first probe

Before the patch:

```sh
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs(["wp-content/plugins/f\\oo/block.json"]); foreach (["wp-content/plugins/f"=>true,"wp-content/plugins/foo"=>true,"wp-content/plugins/foo/block.json"=>false,"wp-content/plugins/f\\oo/block.json"=>false] as $p=>$d) { echo $p, " ", $d?"dir":"file", " => ", $s->includesPath($p,$d)?"yes":"no", "\n"; }'
```

reported the two directories as `no` while both files were `yes`.

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 326 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `2 test files, 529 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7258
  assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; foreach (["pathspecEscapedBytePrefixDirectoryTraversable", "pathspecEscapedByteDirectoryTraversable", "pathspecEscapedByteVerbatimDirectoryIncluded", "pathspecEscapedByteEscapedFileIncluded", "pathspecEscapedByteVerbatimFileIncluded", "pathspecEscapedSlashDirectoryTraversable", "pathspecEscapedSlashFileIncluded"] as $key) { if (($out[$key] ?? null) !== true) { fwrite(STDERR, $key . " failed\n"); exit(1); } } echo "sparse escaped byte traversal example ok\n";'`
  reported `sparse escaped byte traversal example ok`.
- `git diff --check -- lanes/gitoxide` passed.

Root harness: not run - isolated micro-slice.

## Non-overlap

This extends sparse-checkout pathspec parity without repeating cone rules,
absolute/backslash pathspec normalization, POSIX class fallback, reversed
bracket ranges, double-star component boundaries, negative wildcard traversal,
directory-only exclusions, tree pathspec walking, transport, protocol,
pack/object, reference, or merge behavior.

## Dependency closure

No new support component is needed. The patch reuses the lane-local PHP
sparse-checkout pathspec parser, PCRE-backed wildmatch translator, tree
filtering, WordPress example, and PHP test harness. It does not shell out to
Git, run live provider tests, read credentials, or require a shared support
activation gate.
