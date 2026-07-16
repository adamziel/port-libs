# Tree Pathspec Absolute Root Walk Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T014322Z`

Base accepted HEAD: `388d75493f253681c7862bcbbc85820a181fa9e0`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/pattern.rs`
  at upstream commit `87433ed33eee9ba974111d20b854f6acb07cd4a6` normalizes
  absolute pathspecs by stripping the worktree root, rejects absolute paths
  outside that root, and rejects relative components that would leave the
  worktree after root stripping.
- The same source computes `prefix_len` for absolute pathspecs from normalized
  path components. For `icase` pathspecs, `gix-pathspec/src/search/matching.rs`
  applies that prefix as a byte-exact guard before case-folded matching.
- Prior sparse-checkout slices covered this absolute-root behavior for
  sparse rules only; this slice applies it to `PathspecSearch` and
  `TreePathspecWalk`.

## Native Behavior

- `PathspecSearch::fromSpecs()` now accepts optional `root:` for worktree-root
  absolute pathspec normalization.
- Absolute pathspecs inside the root are stripped to repository-relative paths
  before matching and pruning, without prepending the caller prefix.
- Absolute pathspecs outside the root, relative roots, and root-stripped paths
  that escape through `..` now raise `InvalidArgumentException`.
- Absolute wildcard prefixes are preserved as case-sensitive prefixes under
  `icase`, matching upstream's `prefix_len` behavior.
- The WordPress tree-pathspec example now records absolute deployment-root
  selection for a plugin file and root `index.php`, plus rejected outside-root
  and relative-root inputs.

## Verification

- Focused pathspec tree walk:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files / 193 assertions / 0 failures`.
- Related pathspec/attributes/sparse guard:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files / 645 assertions / 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files / 6759 assertions / 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` passed.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec absolute root example ok`.
- Whitespace:
  `git diff --check -- lanes/gitoxide` exited `0`.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This extends accepted tree/pathspec walking without repeating empty-search
materialization, default search modes, prefix/case matching, parent-component
normalization, raw tree-entry component matching, longest-common-directory
hints, wildcard/POSIX class matching, attribute filters, sparse checkout,
tree-merge, pack, object database, reference, protocol, or transport behavior.

No new support component is needed. The slice reuses the native PHP pathspec
parser/search implementation, in-memory tree traversal, the existing WordPress
tree-pathspec example, and the local upstream Gitoxide checkout for source
truth; it does not shell out to Git or require live credentials.
