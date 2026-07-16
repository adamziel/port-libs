# Sparse checkout double-star component-boundary parity

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T002506Z`

Base accepted HEAD: `5b87111468b46af8cd72097f10d11bf759b0ca92`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6`: under
  `NO_MATCH_SLASH_LITERAL`, a run of `**` only crosses `/` when it is at a
  path-component boundary and is followed by end-of-pattern, `/`, or an
  escaped `/`. Mid-component `**` behaves like a component-local wildcard.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  maps `SearchMode::PathAwareGlob` to `NO_MATCH_SLASH_LITERAL`, while
  shell-glob pathspecs use the empty wildmatch mode and may cross `/`.

## Native PHP delta

- `SparseCheckoutSpec::globRegex()` now treats path-aware `**` as
  slash-crossing only when it is a whole path component.
- Sparse-checkout pathspecs now distinguish:
  - `:(glob)wp-content/**.php`, which matches `wp-content/index.php` but not
    `wp-content/plugins/loader.php`;
  - `:(glob)wp-content/**/loader.php`, which crosses directory components;
  - `:(glob)wp-content/plugins**/loader.php`, which matches a single path
    component such as `plugins-vendor` but not `plugins/vendor`.
- The WordPress sparse-checkout example records the same component-boundary
  behavior for deployment materialization.

## Red-first probe

Before the change, this current-base probe returned `[true, true]`:

```sh
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs([":(glob)wp-content/**.php"]); var_export([$s->includesPath("wp-content/loader.php", false), $s->includesPath("wp-content/plugins/loader.php", false)]); echo "\n";'
```

Gitoxide wildmatch expects the nested plugin path to be excluded for that
path-aware mid-component `**`.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `1 test files, 282 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 6499
  assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; if (!$out["pathspecDoubleStarComponentLocalNestedFileSkipped"] || !$out["pathspecDoubleStarComponentDirectoryGlobIncluded"] || !$out["pathspecDoubleStarMidComponentSiblingIncluded"]) { fwrite(STDERR, "sparse checkout double-star example failed\n"); exit(1); } echo "sparse checkout double-star example ok\n";'`
  reported `sparse checkout double-star example ok`.
- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php && php -l lanes/gitoxide/tests/SparseCheckoutTest.php && php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`
  passed with no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "gitoxide json ok\n";'`
  reported `gitoxide json ok`.
- `git diff --check -- lanes/gitoxide` exited `0`.

## Non-overlap

This extends accepted sparse-checkout pathspec behavior beyond prefix,
directory-type, negative wildcard traversal, absolute-root, absolute wildcard
icase, default search-mode, environment defaults, bracket/wildmatch, POSIX
class, and invalid-class fallback slices. It does not touch protocol,
transport, pack/object database, references, tree merge, partial clone, URL,
or credential helper behavior.

## Dependency closure

No new support component is needed. This reuses the existing lane-local
pathspec parser, sparse checkout matcher, tree filtering, PHP PCRE byte
matching, and WordPress sparse-checkout example.
