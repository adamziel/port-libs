# Tree Pathspec Prefix Case Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T091628Z`

Base accepted HEAD: `0098ded681a4eb1c42c3ee09d87f3167111f8b69`

## Upstream Source Truth

- `gix-pathspec/src/pattern.rs`: normalized pathspecs retain a `prefix_len`
  that marks the caller working-directory prefix.
- `gix-pathspec/src/search/init.rs`: `common_prefix_len()` uses `prefix_len`
  for `ICASE` pathspecs, so the caller prefix remains case-sensitive.
- `gix-pathspec/src/search/matching.rs`: matching and pruning first compare the
  common prefix case-sensitively, then apply case folding only to the
  pathspec-controlled suffix.
- `gix-pathspec/tests/search/mod.rs`: `prefixes_are_always_case_sensitive`
  covers `:(icase)` pathspecs rooted at a mixed-case prefix and relative
  `..` pathspecs escaping that prefix.

## Native Behavior

- `PathspecPattern` now records the normalized prefix length carried from
  `PathspecSearch::fromSpecs(..., $prefix)`.
- `PathspecSearch` keeps that prefix as the inclusive common prefix for
  `:(icase)` patterns, reports it from `prefixDirectory()`, and uses it for
  `directoryMatchesPrefix()` pruning.
- Prefix normalization now subtracts `..` components before setting the
  preserved prefix length, matching the upstream case where
  `:(icase)../bar` under prefix `fOo` can match top-level `BAR` but not
  `fOo/BAR`.
- `examples/wordpress-tree-pathspec-walk.php` records a WordPress deployment
  guard showing that a mixed-case `WP-CONTENT` prefix is not folded to
  `wp-content` even when the pathspec suffix is `:(icase)`.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs([":(icase)bar"], "FOO")` matched `foo/bar`,
  reported empty `commonPrefix()` / `prefixDirectory()`, and allowed
  `canMatch("foo", true)`.
- Focused test after change:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 73 assertions / 0 failures`.
- Full Gitoxide lane guard after change:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `38 test files / 3811 assertions / 0 failures`.
- Example smoke after change:
  `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited 0.

## Non-Overlap And Dependency Closure

This extends the accepted `a80a1644` tree pathspec walking slice without
repeating nil/top/exclude/literal/glob traversal, sparse checkout wildmatch,
attributes pathspecs, config include pathspecs, protocol v2, pack, object, ref,
or transport work. It is bounded to upstream prefix case semantics used by
pathspec tree pruning.

No new support component is needed. The slice reuses native PHP byte-string
pathspec parsing/search, tree traversal, and lane-local test fixtures.
