# Tree Pathspec Malformed POSIX Class Parity - 2026-06-01

Micro-slice: `gitoxide-tree-pathspec-walk-parity-20260601T050944Z`

Base accepted HEAD: `b6e9f0ce57867f58750508c9437be4ae03b4d9e1`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-glob/src/wildmatch.rs`
  at `87433ed33eee9ba974111d20b854f6acb07cd4a6` only accepts POSIX
  bracket classes with a complete `[[:name:]]` sentinel; malformed POSIX
  openers such as `[[:alpha]` do not become an `alpha` character class.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pathspec/src/search/matching.rs`
  first tries wildmatch and then falls back to verbatim matching for pathspec
  patterns whose wildcard match failed.

## Native Behavior

- `PathspecSearch` now treats malformed POSIX class openers at the beginning of
  a bracket expression as a failed wildcard match, preserving the existing
  verbatim pathspec fallback.
- Tree walks no longer let a malformed deployment pathspec such as
  `:(glob)wp-content/uploads/[[:alpha]/photo.jpg` select `a/photo.jpg` or
  `[/photo.jpg`.
- The WordPress tree-pathspec example records the matching boundary with a
  literal `[[:alpha]` upload directory and adjacent unsafe lookalikes.

## Verification

- Red observation before the source fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  failed `matches gix wildmatch POSIX blank and invalid class boundaries during
  tree walks` with `Expected: false`, `Actual: true`; run summary was
  `1 test files, 226 assertions, 1 failures`.
- Focused after fix:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php`
  passed `1 test files, 233 assertions, 0 failures`.
- Adjacent pathspec guard:
  `php tools/run-tests.php lanes/gitoxide/tests/PathspecTreeWalkTest.php lanes/gitoxide/tests/AttributesPathspecTest.php lanes/gitoxide/tests/SparseCheckoutTest.php`
  passed `3 test files, 789 assertions, 0 failures`.
- Full Gitoxide lane guard:
  `php tools/run-tests.php lanes/gitoxide/tests` passed `40 test files, 7545
  assertions, 0 failures`.
- Syntax:
  `php -l lanes/gitoxide/src/PathspecSearch.php`,
  `php -l lanes/gitoxide/tests/PathspecTreeWalkTest.php`, and
  `php -l lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php` reported
  no syntax errors.
- Example smoke:
  `php -r '$out = require "lanes/gitoxide/examples/wordpress-tree-pathspec-walk.php"; ...'`
  reported `tree pathspec malformed POSIX example ok`.
- Full upstream Cargo workspace runner was not executed for this isolated
  micro-slice.

## Non-Overlap And Dependency Closure

This deepens the accepted tree/pathspec walking cluster without repeating
empty-search materialization, prefixed nil/empty magic, absolute-root
normalization, raw component preservation, newline wildmatch, longest common
directory hints, malformed bracket fallback already covered for generic
classes, attribute filters, sparse checkout, tree merge, pack, object
database, reference, protocol, transport, config, credential, or partial-clone
behavior.

No new support component is needed. The slice reuses the native PHP pathspec
parser/search implementation, in-memory tree traversal, the existing
WordPress tree-pathspec example, and the local upstream Gitoxide checkout for
source-truth reads; it does not shell out to Git or require live credentials.
