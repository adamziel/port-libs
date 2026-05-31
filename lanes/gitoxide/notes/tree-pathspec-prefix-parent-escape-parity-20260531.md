# Gitoxide Tree Pathspec Prefix Parent Escape Parity

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260531T150657Z`

Accepted base: `5042ee5a640251937d88ffe1e25c7b681010f72f`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/init.rs`

`gix-pathspec::Pattern::normalize()` lets relative `..` components consume the
caller prefix, but returns `OutsideOfWorktree` once normalization would escape
above the worktree root. `Search::from_specs()` normalizes every pattern before
it can participate in tree pruning or matching.

## Native PHP Delta

- `PathspecSearch` now rejects normalized pathspec patterns that would leave
  the repository root instead of silently collapsing excess parent components.
- Valid sibling traversal through the caller prefix still works, so a pathspec
  from `wp-content/plugins` may select `../themes/acme/theme.json`.
- The WordPress tree-pathspec example records both the accepted sibling
  traversal and the rejected over-root deployment pathspec.

## Focused Evidence

- Red-first after adding assertions:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  failed with `Expected exception InvalidArgumentException was not thrown`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 118 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `39 test files, 4748 assertions, 0 failures`.
- Syntax and smoke:
  `php -l` passed for the changed PHP source, test, and example files;
  `php lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` exited `0`;
  JSON validation for the manifest/status files passed; and
  `git diff --check -- lanes/gitoxide` passed.

## Dependency Closure

No new support component is needed. This reuses the native PHP pathspec parser,
normalizer, and tree walker; no shared dependency row or activation gate is
proposed.

## Non-Overlap

This extends accepted tree/pathspec walking beyond nil/top/exclude/icase/
literal/glob parsing, prefix/case matching, empty-search walks, default search
modes, wildmatch/POSIX class walks, sparse checkout pathspec handling, and
attributes/pathspec filters. It is bounded to gix-pathspec parent-component
normalization before tree walking and does not touch transport, refs, packs,
objects, merge, sparse checkout, or config include behavior.
