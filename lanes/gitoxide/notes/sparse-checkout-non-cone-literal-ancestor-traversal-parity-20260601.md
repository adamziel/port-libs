# Sparse Checkout Non-Cone Literal Ancestor Traversal Parity - 2026-06-01

## Source Truth

- Upstream Gitoxide commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-index/tests/fixtures/make_index/v3_sparse_index_non_cone.sh` creates a non-cone sparse checkout with `git sparse-checkout set c1/c2 --no-cone`.
- The generated fixture sparse-checkout pattern file contains the literal pattern `c1/c2`.
- `gix-index/src/access/sparse.rs` documents sparse index expansion around non-cone sparse-checkout patterns and skip-worktree entries.
- `gix-pathspec/src/search/matching.rs` keeps directory traversal conservative for paths that can still contain a later positive match.

## Native Delta

Before this slice, a non-cone pattern file containing `c1/c2` included `c1/c2` and descendants but failed to keep the `c1` directory traversable:

```sh
php -r 'require "tools/bootstrap.php"; use PortLibs\Gitoxide\SparseCheckoutSpec; $s=SparseCheckoutSpec::fromNonConePatternFile("c1/c2\n"); var_export([$s->includesPath("c1", true), $s->includesPath("c1", false), $s->includesPath("c1/c2", true), $s->includesPath("c1/c2/a", false)]); echo "\n";'
```

Output before patch:

```php
array (
  0 => false,
  1 => false,
  2 => true,
  3 => true,
)
```

`SparseCheckoutSpec` now lets unmatched non-cone positive rules keep literal ancestor directories traversable when the rule has a real descendant path component. The same-named file path remains skipped. The fallback also preserves existing repeated-slash byte behavior, so a malformed literal like `wp-content/generated///` does not incorrectly materialize `wp-content/generated`.

The WordPress example now covers `wp-content/plugins/gutenberg/src`, proving that root, `wp-content`, `plugins`, and `gutenberg` are traversable while sibling plugin directories and `build` remain skipped.

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php` passed.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php` passed.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` passed.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php` passed: `1 test files, 383 assertions, 0 failures`.
- `php -r '$out = require "lanes/gitoxide/examples/wordpress-sparse-checkout.php"; if (($out["nonConeLiteralAncestorRootEntriesToMaterialize"] ?? null) !== ["wp-content"] || ($out["nonConeLiteralAncestorWpContentEntriesToMaterialize"] ?? null) !== ["plugins"] || ($out["nonConeLiteralAncestorPluginEntriesToMaterialize"] ?? null) !== ["gutenberg"] || ($out["nonConeLiteralAncestorGutenbergEntriesToMaterialize"] ?? null) !== ["src"] || !($out["nonConeLiteralAncestorSrcIncluded"] ?? false) || !($out["nonConeLiteralAncestorBuildSkipped"] ?? false)) { var_export($out); exit(1); } echo "sparse non-cone ancestor example ok\n";'` passed.
- `git diff --check -- lanes/gitoxide` passed.

Focused assertion delta: `SparseCheckoutTest.php` moved from 368 to 383 assertions, adding 15 focused assertions.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This slice is limited to non-cone sparse pattern-file literal ancestor traversal. It does not repeat accepted pathspec magic/default search-mode behavior, absolute-root pathspec normalization, double-star component boundaries, reversed bracket ranges, LF byte matching, negative wildcard traversal, sparse directory-type boundaries, pack-index/MIDX prefix behavior, partial-clone promisor hydration, or transport/reference transaction work.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP sparse-checkout matcher and tree filtering helpers.
