# Sparse Checkout Malformed POSIX Class Parity - 2026-06-01

Micro-slice: `gitoxide-sparse-checkout-patternspec-parity-20260601T083348Z`

Base accepted HEAD: `fb6ad93e7b785565e56cc2b4b387f00d6bf07fd2`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6` only treats a POSIX bracket
  opener as a class when it has the complete `[[:name:]]` sentinel.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  tries wildmatch first and then falls back to verbatim pathspec matching
  when wildcard matching fails.

## Native Delta

- `SparseCheckoutSpec` now treats malformed POSIX class openers such as
  `[[:alpha]` as a failed wildcard match instead of compiling them as an
  `alpha`-like bracket class.
- Sparse checkout pathspecs no longer include `a/photo.jpg`, `A/photo.jpg`,
  or `[/photo.jpg` for `:(glob)wp-content/uploads/[[:alpha]/photo.jpg`.
- The same malformed pathspec still includes the literal
  `wp-content/uploads/[[:alpha]/photo.jpg` through the upstream-style
  verbatim fallback.
- `examples/wordpress-sparse-checkout.php` records the WordPress upload
  materialization boundary for the malformed class and adjacent lookalikes.

## Red-First Evidence

Before the fix, this current-base probe returned `true` for both unintended
wildcard paths:

```sh
php -r 'require "tools/bootstrap.php"; $s=PortLibs\Gitoxide\SparseCheckoutSpec::fromPathspecs([":(glob)wp-content/uploads/[[:alpha]/photo.jpg"]); var_export([$s->includesPath("wp-content/uploads/a/photo.jpg", false), $s->includesPath("wp-content/uploads/[/photo.jpg", false), $s->includesPath("wp-content/uploads/[[:alpha]/photo.jpg", false)]); echo "\n";'
```

## Verification

- `php -l lanes/gitoxide/src/SparseCheckoutSpec.php`: no syntax errors.
- `php -l lanes/gitoxide/tests/SparseCheckoutTest.php`: no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-sparse-checkout.php`: no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/SparseCheckoutTest.php`:
  `1 test files, 363 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`:
  `40 test files, 8306 assertions, 0 failures`.
- WordPress sparse-checkout example smoke:
  `sparse malformed POSIX example ok`.
- `git diff --check -- lanes/gitoxide`: passed.

Full upstream Cargo workspace tests were not run for this isolated micro-slice.

## Non-Overlap And Dependency Closure

This is limited to sparse-checkout pathspec matching. It does not repeat the
accepted tree/pathspec malformed POSIX class fix, sparse POSIX blank/unknown
class handling, reversed-range matching, double-star component boundaries,
absolute-root normalization, negative wildcard traversal, non-cone pattern
file slash parsing, attributes/pathspec filtering, transport, references,
pack/index, object database, tree-merge, config, credential, or partial-clone
behavior.

No new support component is needed. The slice reuses the native PHP sparse
checkout matcher, existing pathspec wildmatch helpers, tree filtering, and
the local upstream Gitoxide checkout for source-truth reads.
