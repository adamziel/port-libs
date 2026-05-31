# Tree Pathspec Empty Search Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T094920Z`

Base accepted HEAD: `39bb58e3950abcc0370640338af645050eeb5116`

## Upstream Source Truth

- `gix-pathspec/src/search/matching.rs`: empty searches match every
  non-empty relative path by yielding the synthetic `Always` match.
- `gix-pathspec/tests/search/mod.rs`: `no_pathspecs_match_everything`
  asserts that an empty pathspec list has no artificial pattern, matches
  `hello`, can still match descendants, and keeps directory-prefix pruning
  open.
- `gix-traverse/src/tree/breadthfirst.rs`: breadth-first tree traversal keeps
  descending while the delegate matcher reports that descendants can still
  match.

## Native Behavior

- `PathspecSearch::match()` now returns an `Always` match when no pathspecs are
  provided, matching upstream's empty-search behavior instead of only allowing
  `canMatch()` traversal.
- `TreePathspecWalk::breadthFirst()` now materializes all blob paths for an
  empty, unprefixed search because each visited path receives the synthetic
  `Always` match.
- `examples/wordpress-tree-pathspec-walk.php` records the WordPress deployment
  smoke for no-pathspec tree replay, including admin files and generated build
  output that were previously read but not emitted.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs([])->match("wp-content/index.php", false)`
  returned `null`, and `isIncluded()` returned `false`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 79 assertions / 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests`
  passed `38 test files / 3995 assertions / 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php`
  reported no syntax errors.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec example ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited 0.
- Root harness: not run - isolated micro-slice.

## Non-Overlap And Dependency Closure

This extends the accepted tree/pathspec walking slices without repeating
nil/top/exclude/literal/glob parsing, sparse checkout pathspecs, attribute
state filters, prefix/case pruning, protocol, object, ref, pack, or transport
behavior. The mapped upstream behavior is limited to empty pathspec search and
its tree-walk materialization consequence.

No new support component is needed. The slice reuses native PHP pathspec search
and in-memory tree traversal; it does not shell out to Git or require live
provider credentials.
