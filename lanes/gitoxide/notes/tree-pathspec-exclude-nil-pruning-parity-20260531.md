# Tree Pathspec Exclude Nil Pruning Parity - 2026-05-31

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T232158Z`

Base accepted HEAD: `afee0853cdadd52fa12dbc1e24d633ac7329910c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/tests/search/mod.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6`:
  `simplified_search_handles_nil` asserts that `:` keeps traversal open while
  `:(exclude)` makes `can_match_relative_path("a", None|Some(false)|Some(true))`
  return `false`.
- `gix-pathspec/src/search/matching.rs`: `can_match_relative_path()` returns
  `!pattern.is_excluded()` when an always-matching pattern matched, before the
  final all-excluded false-positive fallback for non-empty excluded pathspecs.
- The same source keeps non-empty all-excluded pathspecs conservative by
  returning `self.all_patterns_are_excluded` after no concrete excluded pattern
  matched.

## Native Behavior

- `PathspecSearch::canMatch()` now treats `:(exclude)` as an excluded
  always-match that closes traversal instead of falling through the generic
  all-excluded fallback.
- `PathspecSearch::directoryMatchesPrefix()` mirrors the same
  excluded-always rule while preserving upstream's conservative traversal for
  non-empty all-excluded pathspecs such as `:(exclude)a/file`.
- `TreePathspecWalk::breadthFirst()` now prunes an exclude-all pathspec before
  reading subtrees, while ordinary non-empty exclude-only searches still keep
  descendants open for later fallback inclusion.
- `examples/wordpress-tree-pathspec-walk.php` records the WordPress deployment
  smoke for an exclude-all tree replay: no content paths are emitted and no
  subtree reads are attempted.

## Verification

- Red observation before change:
  `PathspecSearch::fromSpecs([":(exclude)"])->canMatch("a", null)` returned
  `true`, and the tree walk could still descend into subtrees even though every
  entry was excluded.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 155 assertions / 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files / 6261
  assertions / 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited `0`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited `0`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap And Dependency Closure

This deepens the accepted tree/pathspec walking cluster without repeating
empty-search materialization, prefix/case matching, longest-common-directory
hints, wildmatch/POSIX classes, sparse-checkout pathspecs, attributes filters,
protocol, object, reference, pack, transport, or merge behavior. The mapped
behavior is limited to upstream exclude-nil pruning and the all-excluded
non-empty fallback boundary in tree pathspec walking.

No new support component is needed. The slice reuses native PHP pathspec
parsing/search, in-memory tree traversal, the existing WordPress tree-pathspec
example, and the local upstream Gitoxide checkout for source-truth reads; it
does not shell out to Git or require live provider credentials.
